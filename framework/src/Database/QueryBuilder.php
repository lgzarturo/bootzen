<?php

/**
 * QueryBuilder.php
 *
 * Constructor de consultas mínimo. Los valores siempre van como parámetros enlazados y los nombres
 * de tablas y columnas se validan y se encierran entre comillas; para SQL libre existen
 * selectRaw() y whereRaw(), que se usan a propósito.
 *
 * update() y delete() exigen al menos un where: así un descuido no modifica toda la tabla.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use InvalidArgumentException;
use LogicException;

/**
 * Consulta fluida sobre una tabla.
 *
 * @example $db->table('tareas')->where('hecha', false)->orderBy('id', 'desc')->limit(10)->get();
 */
final class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'like', 'not like'];

    private const IDENTIFIER = '[A-Za-z_][A-Za-z0-9_]*';

    /**
     * @var list<string>
     */
    private array $columns = ['*'];

    /**
     * @var list<mixed>
     */
    private array $selectBindings = [];

    /**
     * @var list<array{boolean: string, sql: string}>
     */
    private array $wheres = [];

    /**
     * @var list<mixed>
     */
    private array $whereBindings = [];

    /**
     * @var list<string>
     */
    private array $joins = [];

    /**
     * @var list<string>
     */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(private readonly Connection $connection, private readonly string $table)
    {
    }

    /**
     * Columnas a seleccionar. Admite 'tabla.columna' y 'columna as alias'.
     */
    public function select(string ...$columns): self
    {
        $this->columns = array_map($this->column(...), $columns === [] ? ['*'] : array_values($columns));
        $this->selectBindings = [];

        return $this;
    }

    /**
     * Agrega una expresión SQL libre a la selección.
     *
     * @param list<mixed> $bindings
     * @example ->selectRaw('count(*) as total')
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->columns = $this->columns === ['*'] ? [$expression] : [...$this->columns, $expression];
        array_push($this->selectBindings, ...$bindings);

        return $this;
    }

    /**
     * Condición AND. Con dos argumentos el operador es '='; comparar con null genera IS NULL.
     *
     * @example ->where('hecha', false)
     * @example ->where('precio', '>=', 100)
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        return $this->addWhere('AND', $column, $operator, $value, func_num_args() === 2);
    }

    /**
     * Condición OR.
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        return $this->addWhere('OR', $column, $operator, $value, func_num_args() === 2);
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            return $this->pushWhere('AND', '0 = 1');
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return $this->pushWhere('AND', "{$this->column($column)} IN ({$placeholders})", $values);
    }

    /**
     * @param list<mixed> $values
     */
    public function whereNotIn(string $column, array $values): self
    {
        if ($values === []) {
            return $this->pushWhere('AND', '1 = 1');
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return $this->pushWhere('AND', "{$this->column($column)} NOT IN ({$placeholders})", $values);
    }

    public function whereNull(string $column): self
    {
        return $this->pushWhere('AND', "{$this->column($column)} IS NULL");
    }

    public function whereNotNull(string $column): self
    {
        return $this->pushWhere('AND', "{$this->column($column)} IS NOT NULL");
    }

    /**
     * Condición SQL libre con parámetros.
     *
     * @param list<mixed> $bindings
     * @example ->whereRaw('lower(email) = ?', [$email])
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        return $this->pushWhere('AND', "({$sql})", $bindings);
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);

        if (! in_array($type, ['INNER', 'LEFT'], true)) {
            throw new InvalidArgumentException("Tipo de join [{$type}] no soportado. Usa INNER o LEFT.");
        }

        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            $type,
            $this->column($table),
            $this->column($first),
            $this->operator($operator),
            $this->column($second)
        );

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction);

        if (! in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Dirección de orden [{$direction}] inválida. Usa asc o desc.");
        }

        $this->orders[] = "{$this->column($column)} {$direction}";

        return $this;
    }

    /**
     * Ordena de más reciente a más antiguo.
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $query = clone $this;

        return $query->limit(1)->get()[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $id, string $key = 'id'): ?array
    {
        $query = clone $this;

        return $query->where($key, $id)->first();
    }

    /**
     * Valor de una columna de la primera fila.
     */
    public function value(string $column): mixed
    {
        $query = clone $this;
        $row = $query->select($column)->first();

        return $row === null ? null : reset($row);
    }

    /**
     * Valores de una columna.
     *
     * @return list<mixed>
     */
    public function pluck(string $column): array
    {
        $query = clone $this;

        return array_map(static fn (array $row): mixed => reset($row), $query->select($column)->get());
    }

    public function count(): int
    {
        $query = clone $this;
        $query->columns = ['COUNT(*) AS aggregate'];
        $query->selectBindings = [];
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;

        return (int) $this->connection->scalar($query->toSql(), $query->getBindings());
    }

    public function exists(): bool
    {
        return $this->first() !== null;
    }

    /**
     * Página de resultados.
     *
     * @return Paginator<array<string, mixed>>
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = $this->count();
        $query = clone $this;

        return new Paginator($query->limit($perPage)->offset(($page - 1) * $perPage)->get(), $total, $perPage, $page);
    }

    /**
     * Inserta una fila y devuelve el id generado.
     *
     * @param array<string, mixed> $values
     * @throws InvalidArgumentException Si no hay valores
     */
    public function insert(array $values): string
    {
        if ($values === []) {
            throw new InvalidArgumentException('insert() necesita al menos una columna.');
        }

        $columns = implode(', ', array_map($this->column(...), array_keys($values)));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return $this->connection->insert(
            "INSERT INTO {$this->column($this->table)} ({$columns}) VALUES ({$placeholders})",
            array_values($values)
        );
    }

    /**
     * Actualiza las filas que cumplen los where y devuelve cuántas cambió.
     *
     * @param array<string, mixed> $values
     * @throws LogicException Si no hay where
     */
    public function update(array $values): int
    {
        $this->guardMassChange('update');

        if ($values === []) {
            return 0;
        }

        $sets = implode(', ', array_map(fn (string $column): string => "{$this->column($column)} = ?", array_keys($values)));

        return $this->connection->statement(
            "UPDATE {$this->column($this->table)} SET {$sets}{$this->compileWheres()}",
            [...array_values($values), ...$this->whereBindings]
        );
    }

    /**
     * Borra las filas que cumplen los where y devuelve cuántas borró.
     *
     * @throws LogicException Si no hay where
     */
    public function delete(): int
    {
        $this->guardMassChange('delete');

        return $this->connection->statement(
            "DELETE FROM {$this->column($this->table)}{$this->compileWheres()}",
            $this->whereBindings
        );
    }

    /**
     * SQL de la consulta SELECT (con ? en lugar de valores).
     */
    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->column($this->table);

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->compileWheres();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            // SQLite y MySQL exigen LIMIT para usar OFFSET
            $sql .= ($this->limit === null ? ' LIMIT ' . PHP_INT_MAX : '') . ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Valores enlazados de la consulta SELECT, en orden.
     *
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        return [...$this->selectBindings, ...$this->whereBindings];
    }

    private function addWhere(string $boolean, string $column, mixed $operator, mixed $value, bool $short): self
    {
        if ($short) {
            [$operator, $value] = ['=', $operator];
        }

        if (! is_string($operator)) {
            throw new InvalidArgumentException('El operador de where() debe ser un string, por ejemplo \'=\' o \'>=\'.');
        }

        $operator = $this->operator($operator);

        if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
            return $this->pushWhere($boolean, $this->column($column) . ($operator === '=' ? ' IS NULL' : ' IS NOT NULL'));
        }

        return $this->pushWhere($boolean, "{$this->column($column)} {$operator} ?", [$value]);
    }

    /**
     * @param list<mixed> $bindings
     */
    private function pushWhere(string $boolean, string $sql, array $bindings = []): self
    {
        $this->wheres[] = ['boolean' => $boolean, 'sql' => $sql];
        array_push($this->whereBindings, ...$bindings);

        return $this;
    }

    private function compileWheres(): string
    {
        $sql = '';

        foreach ($this->wheres as $index => $where) {
            $sql .= ($index === 0 ? ' WHERE ' : " {$where['boolean']} ") . $where['sql'];
        }

        return $sql;
    }

    private function operator(string $operator): string
    {
        $operator = strtolower(trim($operator));

        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException(
                "Operador [{$operator}] no permitido. Usa: " . implode(', ', self::OPERATORS) . '.'
            );
        }

        return strtoupper($operator);
    }

    /**
     * Valida y encierra entre comillas un identificador: 'columna', 'tabla.columna', 'tabla.*' o 'x as alias'.
     *
     * @throws InvalidArgumentException Si no es un identificador simple
     */
    private function column(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($identifier === '*') {
            return '*';
        }

        $pattern = '/^(' . self::IDENTIFIER . '(?:\.(?:' . self::IDENTIFIER . '|\*))?)(?:\s+as\s+(' . self::IDENTIFIER . '))?$/i';

        if (preg_match($pattern, $identifier, $match) !== 1) {
            throw new InvalidArgumentException(
                "[{$identifier}] no es un nombre de columna o tabla válido. Para expresiones usa selectRaw() o whereRaw()."
            );
        }

        $quoted = $this->connection->quote($match[1]);

        return isset($match[2]) ? $quoted . ' AS ' . $this->connection->quote($match[2]) : $quoted;
    }

    /**
     * @throws LogicException Si la consulta no tiene where
     */
    private function guardMassChange(string $operation): void
    {
        if ($this->wheres === []) {
            throw new LogicException(
                "{$operation}() sin where afectaría todas las filas de [{$this->table}]. "
                . "Si es intencional, agrega ->whereRaw('1 = 1')."
            );
        }
    }
}
