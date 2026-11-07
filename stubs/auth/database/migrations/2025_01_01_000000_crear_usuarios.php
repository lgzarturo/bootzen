<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Migration;

/**
 * Usuarios y tokens de recuperación de contraseña (scaffold --auth). SQL para SQLite y MySQL.
 */
return new class () extends Migration {
    public function up(Connection $db): void
    {
        if ($db->driver() === 'mysql') {
            // La colación por defecto de MySQL ya compara sin distinguir mayúsculas: el email es único sin importar cómo se escriba
            $db->exec(<<<'SQL'
                CREATE TABLE users (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    nombre VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                )
                SQL);

            $db->exec(<<<'SQL'
                CREATE TABLE password_resets (
                    email VARCHAR(255) PRIMARY KEY,
                    token VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL
                )
                SQL);

            return;
        }

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
