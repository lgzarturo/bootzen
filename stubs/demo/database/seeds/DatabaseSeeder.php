<?php

/**
 * Datos de la demo: php bin/bootzen db:seed
 *
 * Crea a Ana (ana@ejemplo.com / secreto-demo) con 50 tareas de ejemplo (database/factories).
 */

declare(strict_types=1);

use App\Models\Tarea;
use App\Models\User;
use BootZen\Auth\Hash;
use BootZen\Database\Connection;
use BootZen\Database\Seeder;

return new class () extends Seeder {
    public function run(Connection $db): void
    {
        $ana = User::where('email', 'ana@ejemplo.com')->first()
            ?? User::factory()->create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-demo')]);

        Tarea::factory()->count(50)->create(['user_id' => $ana->id]);
    }
};
