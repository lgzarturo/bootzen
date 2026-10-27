<?php

declare(strict_types=1);

require_once __DIR__ . '/../framework/bootstrap.php';

/**
 * Crea un directorio temporal vacío para un test.
 */
function makeTempDir(string $prefix = 'bootzen-test'): string
{
    $directory = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6));
    mkdir($directory, 0775, true);

    return $directory;
}

/**
 * Borra un directorio y todo su contenido.
 */
function removeDir(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}

/**
 * Escribe un archivo creando los directorios intermedios.
 */
function writeFile(string $path, string $contents): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    file_put_contents($path, $contents);
}

/**
 * Elimina variables de entorno creadas por un test.
 */
function forgetEnv(string ...$keys): void
{
    foreach ($keys as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
}

/**
 * Conexión SQLite en memoria con una tabla de tareas de ejemplo.
 */
function memoryDb(bool $withTasks = true): BootZen\Database\Connection
{
    $db = BootZen\Database\Connection::fromConfig(['driver' => 'sqlite', 'database' => ':memory:'], sys_get_temp_dir());

    if ($withTasks) {
        $db->exec(<<<'SQL'
            CREATE TABLE proyectos (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL);
            CREATE TABLE tareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proyecto_id INTEGER REFERENCES proyectos(id) ON DELETE CASCADE,
                titulo TEXT NOT NULL,
                hecha INTEGER NOT NULL DEFAULT 0,
                prioridad INTEGER,
                meta TEXT,
                vence_en TEXT,
                created_at TEXT,
                updated_at TEXT
            );
            SQL);
    }

    return $db;
}

/**
 * Todo lo que se escribió en un stream de salida (para probar comandos de consola).
 *
 * @param resource $stream
 */
function consoleOutput($stream): string
{
    rewind($stream);

    return (string) stream_get_contents($stream);
}
