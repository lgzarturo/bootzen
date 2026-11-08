<?php

/**
 * Factory.php
 *
 * Base de las factories de database/factories/: describen una fila de ejemplo de un modelo para
 * crear registros en tests y seeders sin escribir cada valor.
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
use LogicException;

/**
 * Un valor de definition() puede ser otra factory (se crea antes y se usa su id) o un closure que
 * recibe los demás valores ya resueltos. A diferencia de Modelo::create(), la factory asigna todas
 * las columnas, también las que no están en $fillable (user_id, role...).
 *
 * @template TModel of Model
 *
 * @example
 *   final class TareaFactory extends Factory
 *   {
 *       protected string $model = Tarea::class;
 *
 *       public function definition(): array
 *       {
 *           return ['user_id' => User::factory(), 'titulo' => fake()->sentence(), 'hecha' => fake()->boolean(30)];
 *       }
 *   }
 *
 *   Tarea::factory()->create();                         // una tarea con su usuario
 *   Tarea::factory()->count(5)->create(['user_id' => $ana->id]);
 *   Tarea::factory()->state(['hecha' => true])->make(); // sin guardar
 */
abstract class Factory
{
    /**
     * Modelo que crea esta factory.
     *
     * @var class-string<TModel>
     */
    protected string $model;

    /**
     * @var list<array<string, mixed>|Closure(array<string, mixed>, int): array<string, mixed>>
     */
    private array $states = [];

    /**
     * @var list<Closure(TModel): void>
     */
    private array $afterCreating = [];

    final public function __construct()
    {
    }

    /**
     * Valores de una fila de ejemplo. Usa fake() para que cada registro sea distinto.
     *
     * @return array<string, mixed>
     */
    abstract public function definition(): array;

    /**
     * Cambia valores de la definición. Un closure recibe los valores y la posición en count().
     *
     * @param array<string, mixed>|Closure(array<string, mixed>, int): array<string, mixed> $state
     * @return static
     * @example Tarea::factory()->state(fn (array $valores, int $i) => ['titulo' => "Tarea {$i}"])->count(3)->create();
     */
    public function state(array|Closure $state): static
    {
        $clone = clone $this;
        $clone->states[] = $state;

        return $clone;
    }

    /**
     * Ejecuta $callback con cada modelo recién guardado (por ejemplo, para crearle registros hijos).
     *
     * @param Closure(TModel): void $callback
     * @return static
     */
    public function afterCreating(Closure $callback): static
    {
        $clone = clone $this;
        $clone->afterCreating[] = $callback;

        return $clone;
    }

    /**
     * Varios registros: ->count(10)->create() devuelve una lista.
     *
     * @return FactoryBatch<TModel>
     */
    public function count(int $count): FactoryBatch
    {
        return new FactoryBatch($this, $count);
    }

    /**
     * Un modelo con los valores de la definición, sin guardarlo.
     *
     * @param array<string, mixed> $attributes Valores que reemplazan a los de la definición
     * @return TModel
     */
    public function make(array $attributes = []): Model
    {
        return $this->build($attributes, 0, false);
    }

    /**
     * Crea y guarda un registro.
     *
     * @param array<string, mixed> $attributes Valores que reemplazan a los de la definición
     * @return TModel
     */
    public function create(array $attributes = []): Model
    {
        return $this->build($attributes, 0, true);
    }

    /**
     * Arma el modelo número $index y, si $persist, lo guarda.
     *
     * @internal Lo usan make(), create() y FactoryBatch.
     * @param array<string, mixed> $attributes
     * @return TModel
     */
    public function build(array $attributes, int $index, bool $persist): Model
    {
        if (! isset($this->model) || ! is_subclass_of($this->model, Model::class)) {
            throw new LogicException(static::class . ' debe definir protected string $model = Modelo::class;');
        }

        $values = $this->definition();
        foreach ($this->states as $state) {
            $values = array_replace($values, $state instanceof Closure ? $state($values, $index) : $state);
        }
        $values = array_replace($values, $attributes);

        $model = new $this->model();
        foreach ($values as $key => $value) {
            if ($value instanceof self) {
                $value = $value->create()->getKey();
            } elseif ($value instanceof Closure) {
                $value = $value($values);
            }

            $values[$key] = $value;
            $model->setAttribute($key, $value);
        }

        if ($persist) {
            $model->save();

            foreach ($this->afterCreating as $callback) {
                $callback($model);
            }
        }

        return $model;
    }
}
