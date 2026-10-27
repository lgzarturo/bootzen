<?php

/**
 * Datos de la demo: php bin/bootzen db:seed
 *
 * Crea a Ana (ana@ejemplo.com / secreto-demo) con algunas tareas.
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
            ?? User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-demo')]);

        $tareas = [
            ['titulo' => 'Comprar pan', 'hecha' => true],
            ['titulo' => 'Pagar la luz', 'vence' => date('Y-m-d', strtotime('+3 days'))],
            ['titulo' => 'Llamar al dentista', 'notas' => 'Preguntar por la limpieza de los martes.'],
        ];

        foreach ($tareas as $tarea) {
            Tarea::create(['user_id' => $ana->id] + $tarea);
        }
    }
};
