<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Migration;

/**
 * Verificación de correo (users.email_verified_at) y tokens de "recordarme" (scaffold --auth).
 * SQL para SQLite y MySQL.
 */
return new class () extends Migration {
    public function up(Connection $db): void
    {
        if ($db->driver() === 'mysql') {
            $db->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL');
            $db->exec(<<<'SQL'
                CREATE TABLE remember_tokens (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    selector CHAR(24) NOT NULL UNIQUE,
                    validator_hash CHAR(64) NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NULL,
                    INDEX remember_tokens_user_id (user_id),
                    CONSTRAINT remember_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                )
                SQL);

            return;
        }

        $db->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT');
        $db->exec(<<<'SQL'
            CREATE TABLE remember_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                selector TEXT NOT NULL UNIQUE,
                validator_hash TEXT NOT NULL,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                expires_at TEXT NOT NULL,
                created_at TEXT
            )
            SQL);
        $db->exec('CREATE INDEX remember_tokens_user_id ON remember_tokens (user_id)');
    }

    public function down(Connection $db): void
    {
        $db->exec('DROP TABLE remember_tokens');
        $db->exec('ALTER TABLE users DROP COLUMN email_verified_at');
    }
};
