<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tarea;
use App\Models\User;
use BootZen\Database\Factory;

/**
 * Tareas de ejemplo: Tarea::factory()->count(10)->create(['user_id' => $ana->id]). Sin user_id
 * crea también a su dueño.
 *
 * @extends Factory<Tarea>
 */
final class TareaFactory extends Factory
{
    protected string $model = Tarea::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'titulo' => fake()->sentence(),
            'notas' => fake()->boolean(40) ? fake()->paragraph(2) : null,
            'hecha' => fake()->boolean(30),
            'vence' => fake()->boolean(60) ? fake()->date('-1 week', '+1 month') : null,
        ];
    }

    /**
     * Tareas ya hechas: Tarea::factory()->hechas()->count(3)->create().
     */
    public function hechas(): self
    {
        return $this->state(['hecha' => true]);
    }
}
