<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Migration;

/**
 * Tareas de cada usuario (app de demostración).
 */
return new class () extends Migration {
    public function up(Connection $db): void
    {
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
