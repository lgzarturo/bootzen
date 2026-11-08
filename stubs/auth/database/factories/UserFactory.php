<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use BootZen\Auth\Hash;
use BootZen\Database\Factory;

/**
 * Usuarios de ejemplo: User::factory()->create(['email' => 'ana@ejemplo.com']).
 *
 * Todos tienen la contraseña de PASSWORD (el hash se calcula una sola vez, porque es lento a
 * propósito).
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    public const PASSWORD = 'secreto-largo';

    protected string $model = User::class;

    private static ?string $hash = null;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'email' => fake()->unique()->email(),
            'password' => self::$hash ??= Hash::make(self::PASSWORD),
        ];
    }
}
