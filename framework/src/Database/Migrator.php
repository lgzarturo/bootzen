<?php

/**
 * Migrator.php
 *
 * Ejecuta las migraciones de database/migrations en orden de nombre de archivo
 * (AAAA_MM_DD_HHMMSS_descripcion.php) y registra cada lote en la tabla migrations.
 *
 * En SQLite cada migración corre en una transacción: si falla, no deja el esquema a medias.
 * MySQL confirma los cambios de esquema al instante, así que ahí no hay esa garantía.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use UnexpectedValueException;

/**
 * Ejecutor de migraciones.
 */
final class Migrator
{
    private const TABLE = 'migrations';

    /**
     * @param string $path Directorio de migraciones
     */
    public function __construct(private readonly Connection $db, private readonly string $path)
    {
    }

    /**
     * Ejecuta las migraciones pendientes en un nuevo lote. Devuelve sus nombres.
     *
     * @return list<string>
     */
    public function run(): array
    {
        $this->ensureTable();
        $ran = $this->ranMigrations();
        $pending = array_diff_key($this->files(), $ran);

        if ($pending === []) {
            return [];
        }

        $batch = ($ran === [] ? 0 : max($ran)) + 1;

        foreach ($pending as $name => $file) {
            $this->apply($name, fn () => $this->load($file)->up($this->db));
            $this->db->table(self::TABLE)->insert([
                'migration' => $name,
                'batch' => $batch,
                'ran_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return array_keys($pending);
    }

    /**
     * Revierte los últimos lotes (uno por defecto). Devuelve los nombres revertidos.
     *
     * @return list<string>
     * @throws UnexpectedValueException Si falta el archivo de una migración registrada
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureTable();
        $ran = $this->ranMigrations();

        if ($ran === []) {
            return [];
        }

        $batches = array_slice(array_unique(array_values($ran)), -max(1, $steps));
        $toRollback = array_reverse(array_keys(array_filter($ran, static fn (int $batch): bool => in_array($batch, $batches, true))));
        $files = $this->files();

        foreach ($toRollback as $name) {
            if (! isset($files[$name])) {
                throw new UnexpectedValueException("No existe el archivo de la migración [{$name}] en {$this->path}.");
            }

            $this->apply($name, fn () => $this->load($files[$name])->down($this->db));
            $this->db->table(self::TABLE)->where('migration', $name)->delete();
        }

        return $toRollback;
    }

    /**
     * Estado de cada migración.
     *
     * @return list<array{migration: string, ran: bool, batch: int|null}>
     */
    public function status(): array
    {
        $this->ensureTable();
        $ran = $this->ranMigrations();
        $names = array_unique([...array_keys($this->files()), ...array_keys($ran)]);
        sort($names);

        return array_map(
            static fn (string $name): array => ['migration' => $name, 'ran' => isset($ran[$name]), 'batch' => $ran[$name] ?? null],
            $names
        );
    }

    /**
     * Borra todas las tablas y vuelve a ejecutar todas las migraciones.
     *
     * @return list<string>
     */
    public function fresh(): array
    {
        $this->dropAllTables();

        return $this->run();
    }

    /**
     * Borra todas las tablas de la base de datos.
     */
    public function dropAllTables(): void
    {
        if ($this->db->driver() === 'mysql') {
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($this->db->select('SHOW TABLES') as $row) {
                $this->db->exec('DROP TABLE ' . $this->db->quote((string) reset($row)));
            }
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');

            return;
        }

        $tables = $this->db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        $this->db->exec('PRAGMA foreign_keys = OFF');

        foreach ($tables as $table) {
            $this->db->exec('DROP TABLE IF EXISTS ' . $this->db->quote((string) $table['name']));
        }

        $this->db->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * Ejecuta un paso de migración; en SQLite, dentro de una transacción.
     *
     * @param callable(): void $step
     */
    private function apply(string $name, callable $step): void
    {
        try {
            if ($this->db->driver() === 'sqlite') {
                $this->db->transaction(static function () use ($step): void {
                    $step();
                });
            } else {
                $step();
            }
        } catch (\Throwable $e) {
            throw new MigrationException("Falló la migración [{$name}]: {$e->getMessage()}", $e);
        }
    }

    /**
     * Archivos de migración por nombre, ordenados.
     *
     * @return array<string, string>
     */
    private function files(): array
    {
        $files = [];

        foreach (glob(rtrim($this->path, '/') . '/*.php') ?: [] as $file) {
            $files[basename($file, '.php')] = $file;
        }

        ksort($files);

        return $files;
    }

    /**
     * @throws UnexpectedValueException Si el archivo no retorna una Migration
     */
    private function load(string $file): Migration
    {
        $migration = require $file;

        if (! $migration instanceof Migration) {
            throw new UnexpectedValueException(
                'La migración ' . basename($file) . ' debe retornar una clase que extienda ' . Migration::class
                . ': return new class () extends Migration { ... };'
            );
        }

        return $migration;
    }

    /**
     * Migraciones ejecutadas => lote, en orden de ejecución.
     *
     * @return array<string, int>
     */
    private function ranMigrations(): array
    {
        $ran = [];

        foreach ($this->db->table(self::TABLE)->orderBy('batch')->orderBy('migration')->get() as $row) {
            $ran[(string) $row['migration']] = (int) $row['batch'];
        }

        return $ran;
    }

    private function ensureTable(): void
    {
        $id = $this->db->driver() === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . " (id {$id}, migration VARCHAR(255) NOT NULL UNIQUE, "
            . 'batch INTEGER NOT NULL, ran_at VARCHAR(19) NOT NULL)'
        );
    }
}
