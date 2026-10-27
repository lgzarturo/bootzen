<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Migration;

/**
 * Tareas de cada usuario (app de demostración). SQL para SQLite y MySQL.
 */
return new class () extends Migration {
    public function up(Connection $db): void
    {
        if ($db->driver() === 'mysql') {
            $db->exec(<<<'SQL'
                CREATE TABLE tareas (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    titulo VARCHAR(255) NOT NULL,
                    notas TEXT NULL,
                    hecha TINYINT(1) NOT NULL DEFAULT 0,
                    vence DATE NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    INDEX idx_tareas_user_id (user_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
                SQL);

            return;
        }

        $db->exec(<<<'SQL'
            CREATE TABLE tareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                titulo TEXT NOT NULL,
                notas TEXT NULL,
                hecha INTEGER NOT NULL DEFAULT 0,
                vence TEXT NULL,
                created_at TEXT,
                updated_at TEXT
            )
            SQL);

        $db->exec('CREATE INDEX idx_tareas_user_id ON tareas (user_id)');
    }

    public function down(Connection $db): void
    {
        $db->exec('DROP TABLE tareas');
    }
};
