<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Migration;

/**
 * Rol de cada usuario (permisos en config/auth.php, roles). Todos empiezan como 'usuario'; el primer
 * admin se nombra con php bin/bootzen user:role correo@ejemplo.com admin.
 */
return new class () extends Migration {
    public function up(Connection $db): void
    {
        $db->exec($db->driver() === 'mysql'
            ? "ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'usuario'"
            : "ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'usuario'");
    }

    public function down(Connection $db): void
    {
        $db->exec('ALTER TABLE users DROP COLUMN role');
    }
};
