<?php

/**
 * Connection.php
 *
 * Conexión a la base de datos sobre PDO. SQLite por defecto (WAL, claves foráneas activas y espera
 * ante bloqueos) y MySQL como alternativa. Todas las consultas usan parámetros enlazados.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use Closure;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Conexión PDO con helpers para consultas, transacciones y el query builder.
 *
 * @example $db->select('SELECT * FROM tareas WHERE hecha = ?', [0]);
 * @example $db->table('tareas')->where('hecha', false)->get();
 */
final class Connection
{
    private int $transactions = 0;

    public function __construct(private readonly PDO $pdo, private readonly string $driver)
    {
    }

    /**
     * Crea la conexión a partir de la configuración de config/database.php.
     *
     * @param array<string, mixed> $config Configuración de una conexión
     * @param string $basePath Raíz del proyecto, para rutas relativas de SQLite
     * @throws InvalidArgumentException Si el driver no es soportado
     * @throws QueryException Si no se puede conectar
     */
    public static function fromConfig(array $config, string $basePath): self
    {
        $driver = is_string($config['driver'] ?? null) ? $config['driver'] : 'sqlite';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            return match ($driver) {
                'sqlite' => self::sqlite($config, $basePath, $options),
                'mysql' => self::mysql($config, $options),
                default => throw new InvalidArgumentException(
                    "Driver de base de datos [{$driver}] no soportado. Usa sqlite o mysql."
                ),
            };
        } catch (PDOException $e) {
            throw new QueryException('(conexión)', [], $e);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Driver de la conexión: sqlite o mysql.
     */
    public function driver(): string
    {
        return $this->driver;
    }

    /**
     * Inicia un query builder para una tabla.
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Ejecuta un SELECT y devuelve todas las filas.
     *
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     * @throws QueryException
     */
    public function select(string $sql, array $bindings = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * Ejecuta un SELECT y devuelve la primera fila o null.
     *
     * @param list<mixed> $bindings
     * @return array<string, mixed>|null
     * @throws QueryException
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch(PDO::FETCH_ASSOC);

        /** @var array<string, mixed>|false $row */
        return $row === false ? null : $row;
    }

    /**
     * Ejecuta un SELECT y devuelve el valor de la primera columna de la primera fila.
     *
     * @param list<mixed> $bindings
     * @throws QueryException
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Ejecuta INSERT, UPDATE o DELETE y devuelve las filas afectadas.
     *
     * @param list<mixed> $bindings
     * @throws QueryException
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    /**
     * Ejecuta un INSERT y devuelve el id generado.
     *
     * @param list<mixed> $bindings
     * @throws QueryException
     */
    public function insert(string $sql, array $bindings = []): string
    {
        $this->run($sql, $bindings);

        return (string) $this->pdo->lastInsertId();
    }

    /**
     * Ejecuta SQL sin parámetros (puede tener varias sentencias). Pensado para migraciones.
     *
     * @throws QueryException
     */
    public function exec(string $sql): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            throw new QueryException($sql, [], $e);
        }
    }

    /**
     * Ejecuta el callback dentro de una transacción: confirma si termina bien y revierte si lanza.
     * Las transacciones anidadas se integran a la exterior.
     *
     * @template T
     * @param Closure(Connection): T $callback
     * @return T
     * @throws Throwable Lo que lance el callback, después de revertir
     */
    public function transaction(Closure $callback): mixed
    {
        if ($this->transactions === 0) {
            $this->pdo->beginTransaction();
        }

        $this->transactions++;

        try {
            $result = $callback($this);
            $this->transactions--;

            if ($this->transactions === 0) {
                $this->pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            $this->transactions--;

            if ($this->transactions === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Encierra un identificador (tabla o columna) entre comillas del driver. Admite 'tabla.columna' y '*'.
     */
    public function quote(string $identifier): string
    {
        $quote = $this->driver === 'mysql' ? '`' : '"';

        return implode('.', array_map(
            static fn (string $part): string => $part === '*' ? $part : $quote . str_replace($quote, '', $part) . $quote,
            explode('.', $identifier)
        ));
    }

    /**
     * Prepara y ejecuta una consulta.
     *
     * @param list<mixed> $bindings
     * @throws QueryException
     */
    private function run(string $sql, array $bindings): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql);

            foreach ($bindings as $index => $value) {
                $statement->bindValue($index + 1, ...self::bindable($value));
            }

            $statement->execute();

            return $statement;
        } catch (PDOException $e) {
            throw new QueryException($sql, $bindings, $e);
        }
    }

    /**
     * Convierte un valor a [valor, tipo PDO]. Los booleanos se guardan como 1 o 0.
     *
     * @return array{0: mixed, 1: int}
     */
    private static function bindable(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_bool($value) => [(int) $value, PDO::PARAM_INT],
            is_int($value) => [$value, PDO::PARAM_INT],
            $value instanceof \DateTimeInterface => [$value->format('Y-m-d H:i:s'), PDO::PARAM_STR],
            $value instanceof \Stringable => [(string) $value, PDO::PARAM_STR],
            is_scalar($value) => [(string) $value, PDO::PARAM_STR],
            default => throw new InvalidArgumentException(
                'Valor de tipo ' . get_debug_type($value) . ' no se puede enviar a la base de datos.'
            ),
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, mixed> $options
     */
    private static function sqlite(array $config, string $basePath, array $options): self
    {
        $database = is_string($config['database'] ?? null) ? $config['database'] : 'database/app.sqlite';
        $memory = $database === ':memory:';

        if (! $memory && ! str_starts_with($database, '/')) {
            $database = rtrim($basePath, '/') . '/' . $database;
        }

        if (! $memory && ! is_dir(dirname($database))) {
            mkdir(dirname($database), 0775, true);
        }

        $pdo = new PDO('sqlite:' . $database, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ' . (($config['foreign_keys'] ?? true) ? 'ON' : 'OFF'));
        $pdo->exec('PRAGMA busy_timeout = ' . (int) ($config['busy_timeout'] ?? 5000));

        if (! $memory) {
            $journal = strtoupper(is_string($config['journal_mode'] ?? null) ? $config['journal_mode'] : 'WAL');
            if (! in_array($journal, ['WAL', 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'OFF'], true)) {
                throw new InvalidArgumentException("journal_mode [{$journal}] inválido para SQLite.");
            }

            $pdo->exec("PRAGMA journal_mode = {$journal}");
            $pdo->exec('PRAGMA synchronous = NORMAL');
        }

        return new self($pdo, 'sqlite');
    }

    /**
     * @param array<string, mixed> $config
     * @param array<int, mixed> $options
     */
    private static function mysql(array $config, array $options): self
    {
        $value = static fn (string $key, string $default): string => is_scalar($config[$key] ?? null)
            ? (string) $config[$key]
            : $default;

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $value('host', '127.0.0.1'),
            $value('port', '3306'),
            $value('database', 'app'),
            $value('charset', 'utf8mb4')
        );

        $options[PDO::ATTR_EMULATE_PREPARES] = false;

        return new self(new PDO($dsn, $value('username', 'root'), $value('password', ''), $options), 'mysql');
    }
}
