<?php

/**
 * ModelQuery.php
 *
 * Consulta que devuelve modelos en lugar de arrays. Envuelve al QueryBuilder con los mismos métodos.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

/**
 * Consulta de modelos.
 *
 * @template TModel of Model
 * @example Tarea::where('hecha', false)->latest()->paginate(10, $pagina);
 */
final class ModelQuery
{
    /**
     * @param class-string<TModel> $model
     */
    public function __construct(private readonly string $model, private readonly QueryBuilder $query)
    {
    }

    /**
     * @return self<TModel>
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        func_num_args() === 2 ? $this->query->where($column, $operator) : $this->query->where($column, $operator, $value);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        func_num_args() === 2 ? $this->query->orWhere($column, $operator) : $this->query->orWhere($column, $operator, $value);

        return $this;
    }

    /**
     * @param list<mixed> $values
     * @return self<TModel>
     */
    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);

        return $this;
    }

    /**
     * @param list<mixed> $values
     * @return self<TModel>
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->query->whereNotIn($column, $values);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function whereNotNull(string $column): self
    {
        $this->query->whereNotNull($column);

        return $this;
    }

    /**
     * @param list<mixed> $bindings
     * @return self<TModel>
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->query->whereRaw($sql, $bindings);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function latest(string $column = 'created_at'): self
    {
        $this->query->latest($column);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function limit(int $limit): self
    {
        $this->query->limit($limit);

        return $this;
    }

    /**
     * @return self<TModel>
     */
    public function offset(int $offset): self
    {
        $this->query->offset($offset);

        return $this;
    }

    /**
     * @return list<TModel>
     */
    public function get(): array
    {
        return array_map($this->model::hydrate(...), $this->query->get());
    }

    /**
     * @return TModel|null
     */
    public function first(): ?Model
    {
        $row = $this->query->first();

        return $row === null ? null : $this->model::hydrate($row);
    }

    /**
     * @return TModel
     * @throws ModelNotFoundException 404 si no hay resultados
     */
    public function firstOrFail(): Model
    {
        return $this->first() ?? throw new ModelNotFoundException($this->model);
    }

    /**
     * @return TModel|null
     */
    public function find(int|string $id): ?Model
    {
        $row = $this->query->find($id, $this->model::primaryKey());

        return $row === null ? null : $this->model::hydrate($row);
    }

    /**
     * @return TModel
     * @throws ModelNotFoundException 404 si no existe
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->find($id) ?? throw new ModelNotFoundException($this->model, $id);
    }

    public function count(): int
    {
        return $this->query->count();
    }

    public function exists(): bool
    {
        return $this->query->exists();
    }

    /**
     * @return list<mixed>
     */
    public function pluck(string $column): array
    {
        return $this->query->pluck($column);
    }

    /**
     * @return Paginator<TModel>
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $rows = $this->query->paginate($perPage, $page);

        return new Paginator(
            array_map($this->model::hydrate(...), $rows->items()),
            $rows->total(),
            $rows->perPage(),
            $rows->currentPage()
        );
    }

    /**
     * Actualiza todas las filas de la consulta (sin pasar por $fillable). Requiere un where.
     *
     * @param array<string, mixed> $values
     */
    public function update(array $values): int
    {
        return $this->query->update($values);
    }

    /**
     * Borra todas las filas de la consulta. Requiere un where.
     */
    public function delete(): int
    {
        return $this->query->delete();
    }

    /**
     * Query builder subyacente, para lo que no cubre ModelQuery.
     */
    public function toBase(): QueryBuilder
    {
        return $this->query;
    }
}
