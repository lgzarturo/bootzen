<?php

/**
 * FactoryBatch.php
 *
 * Lo que devuelve Factory::count(): crea o arma varios modelos de una vez.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.5.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use Closure;
use InvalidArgumentException;

/**
 * @template TModel of Model
 *
 * @example $tareas = Tarea::factory()->count(20)->create(['user_id' => $ana->id]);
 */
final class FactoryBatch
{
    /**
     * @param Factory<TModel> $factory
     * @throws InvalidArgumentException Si $count es menor que 1
     */
    public function __construct(private readonly Factory $factory, private readonly int $count)
    {
        if ($count < 1) {
            throw new InvalidArgumentException("count() necesita 1 o más registros, llegó {$count}.");
        }
    }

    /**
     * Igual que Factory::state(), para todos los registros del lote.
     *
     * @param array<string, mixed>|Closure(array<string, mixed>, int): array<string, mixed> $state
     * @return self<TModel>
     */
    public function state(array|Closure $state): self
    {
        return new self($this->factory->state($state), $this->count);
    }

    /**
     * Arma los modelos sin guardarlos.
     *
     * @param array<string, mixed> $attributes
     * @return list<TModel>
     */
    public function make(array $attributes = []): array
    {
        return array_map(fn (int $index): Model => $this->factory->build($attributes, $index, false), range(0, $this->count - 1));
    }

    /**
     * Crea y guarda los registros en una transacción.
     *
     * @param array<string, mixed> $attributes
     * @return list<TModel>
     */
    public function create(array $attributes = []): array
    {
        $build = fn (): array => array_map(fn (int $index): Model => $this->factory->build($attributes, $index, true), range(0, $this->count - 1));

        return Model::connection()->transaction(static fn (): array => $build());
    }
}
