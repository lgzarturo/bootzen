<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Migration;

/**
 * Usuarios y tokens de recuperación de contraseña (scaffold --auth).
 */
return new class () extends Migration {
    public function up(Connection $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password TEXT NOT NULL,
                created_at TEXT,
                updated_at TEXT
            )
            SQL);

        $db->exec(<<<'SQL'
            CREATE TABLE password_resets (
                email TEXT PRIMARY KEY,
                token TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
            SQL);
    }

    public function down(Connection $db): void
    {
        $db->exec('DROP TABLE password_resets');
        $db->exec('DROP TABLE users');
    }
};
