<?php

/**
 * Paginator.php
 *
 * Resultado paginado: los elementos de la página y los datos para armar los enlaces.
 * En vistas se usa con <x-pagination :paginator="$tareas" />; en APIs, se devuelve tal cual (JSON).
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Página de resultados.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final class Paginator implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Parámetros de query string que se conservan en los enlaces (filtros, búsqueda).
     *
     * @var array<string, scalar>
     */
    private array $query = [];

    /**
     * @param list<T> $items Elementos de la página actual
     * @param int $total Total de elementos en todas las páginas
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage
    ) {
    }

    /**
     * @return list<T>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    /**
     * Indica si hay más de una página.
     */
    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function previousPage(): ?int
    {
        return $this->currentPage > 1 ? min($this->currentPage - 1, $this->lastPage()) : null;
    }

    public function nextPage(): ?int
    {
        return $this->currentPage < $this->lastPage() ? $this->currentPage + 1 : null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Conserva parámetros de query string en los enlaces.
     *
     * @param array<string, scalar> $query
     * @return self<T>
     * @example $tareas->appends(['q' => $busqueda])
     */
    public function appends(array $query): self
    {
        $this->query = array_merge($this->query, $query);

        return $this;
    }

    /**
     * Enlace relativo a una página (query string), conservando los parámetros de appends().
     */
    public function url(int $page): string
    {
        return '?' . http_build_query(array_merge($this->query, ['page' => max(1, $page)]));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return array{data: list<mixed>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(
                static fn (mixed $item): mixed => $item instanceof JsonSerializable ? $item->jsonSerialize() : $item,
                $this->items
            ),
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage(),
        ];
    }

    /**
     * @return array{data: list<mixed>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
