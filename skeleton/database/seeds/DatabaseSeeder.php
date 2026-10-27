<?php

/**
 * Seeder principal: php bin/bootzen db:seed
 *
 * Inserta datos de ejemplo o iniciales. Para dividirlo, crea otros archivos en database/seeds
 * y llámalos con $this->call('Nombre').
 */

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Seeder;

return new class () extends Seeder {
    public function run(Connection $db): void
    {
        // $db->table('tareas')->insert(['titulo' => 'Primera tarea']);
    }
};
