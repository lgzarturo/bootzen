<?php

/**
 * Doctor.php
 *
 * bin/bootzen doctor: revisa lo que suele fallar al desplegar (versión de PHP, extensiones, permisos,
 * .env, APP_DEBUG en producción, base de datos, migraciones y CSS compilado) y dice cómo arreglarlo.
 *
 * @package    BootZen\Console
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Console;

use BootZen\Core\Application;
use BootZen\Database\Connection;
use BootZen\Database\Migrator;
use BootZen\Queue\DatabaseQueue;
use BootZen\Schedule\Schedule;
use BootZen\Storage\LocalDisk;
use BootZen\Storage\Storage;
use BootZen\Storage\StorageManager;
use Closure;
use Throwable;

/**
 * Cada revisión es un error (✗, el comando termina con 1), un aviso (!) o algo correcto (✓).
 */
final class Doctor
{
    private int $errors = 0;

    /**
     * @param Closure(string): void $line Escribe una línea de salida
     * @param (Closure(): Schedule)|null $schedule Tareas de routes/schedule.php
     */
    public function __construct(
        private readonly Application $app,
        private readonly Closure $line,
        private readonly ?Closure $schedule = null
    ) {
    }

    public function run(): int
    {
        ($this->line)('Diagnóstico de ' . $this->app->config()->get('app.name', 'la aplicación'));

        $this->checkPhp();
        $this->checkEnvironment();
        $this->checkPermissions();
        $this->checkDatabase();
        $this->checkStorage();
        $this->checkMail();
        $this->checkQueue();
        $this->checkSchedule();
        $this->checkAssets();

        ($this->line)('');
        ($this->line)($this->errors === 0 ? 'Todo listo.' : "Hay {$this->errors} problema(s) que corregir.");

        return $this->errors === 0 ? 0 : 1;
    }

    private function checkPhp(): void
    {
        version_compare(PHP_VERSION, '8.2.0', '>=')
            ? $this->ok('PHP ' . PHP_VERSION)
            : $this->error('PHP ' . PHP_VERSION . ': BootZen necesita PHP 8.2 o superior.');

        $driver = $this->driver();
        $required = ['pdo', $driver === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite', 'mbstring', 'fileinfo', 'ctype', 'json'];
        $missing = array_values(array_filter($required, static fn (string $extension): bool => ! extension_loaded($extension)));

        $missing === []
            ? $this->ok('Extensiones: ' . implode(', ', $required))
            : $this->error('Faltan extensiones de PHP: ' . implode(', ', $missing) . '. Instálalas o actívalas en php.ini.');
    }

    private function checkEnvironment(): void
    {
        if (is_file($this->app->basePath('.env'))) {
            $this->ok('.env presente');
        } elseif (getenv('APP_KEY') !== false) {
            $this->ok('Sin .env: se usan las variables del servidor (Docker, panel del hosting)');
        } else {
            $this->warning('No hay .env: copia .env.example a .env (o define las variables en el servidor).');
        }

        $production = $this->app->config()->get('app.env') === 'production';
        $key = $this->app->config()->get('app.key');

        if (is_string($key) && $key !== '') {
            $this->ok('APP_KEY definida');
        } elseif ($production) {
            $this->error('APP_KEY vacía en producción: genérala con php bin/bootzen key:generate y guárdala en .env.');
        } else {
            $this->warning('APP_KEY vacía: ejecuta php bin/bootzen key:generate.');
        }

        if ($production && $this->app->isDebug()) {
            $this->error('APP_DEBUG=true en producción: muestra el código y la traza de los errores. Ponlo en false.');
        } elseif ($production) {
            $this->ok('APP_DEBUG desactivado en producción');
        }

        if (filter_var($this->app->config()->get('app.installer', false), FILTER_VALIDATE_BOOL)) {
            $this->warning('INSTALL_ENABLED=true: la instalación web está activa. Ponla en false cuando termines.');
        }

        if ($production && ! is_file($this->app->basePath('storage/cache/config.php'))) {
            $this->warning('En producción conviene php bin/bootzen config:cache (y view:cache).');
        }
    }

    private function checkPermissions(): void
    {
        $directories = ['storage', 'storage/logs', 'storage/cache', 'storage/sessions'];

        $database = $this->sqliteDirectory();
        if ($database !== null) {
            $directories[] = $database;
        }

        $problems = [];
        foreach ($directories as $directory) {
            // Si la carpeta no existe todavía, basta con poder crearla en la primera carpeta que sí existe
            $path = str_starts_with($directory, '/') ? $directory : $this->app->basePath($directory);
            while (! is_dir($path) && dirname($path) !== $path) {
                $path = dirname($path);
            }

            if (! is_writable($path)) {
                $problems[] = $directory;
            }
        }

        $problems === []
            ? $this->ok('Permisos de escritura: ' . implode(', ', $directories))
            : $this->error('Sin permiso de escritura en: ' . implode(', ', $problems) . '. El usuario del servidor web debe poder escribir ahí.');
    }

    private function checkDatabase(): void
    {
        try {
            $db = $this->app->make(Connection::class);
            if ($db instanceof Connection) {
                $db->scalar('SELECT 1');
            }

            $this->ok('Conexión a la base de datos (' . $this->driver() . ')');
        } catch (Throwable $e) {
            $this->error('No se pudo conectar a la base de datos: ' . $e->getMessage());

            return;
        }

        try {
            $migrator = $this->app->make(Migrator::class);
            $pending = $migrator instanceof Migrator
                ? count(array_filter($migrator->status(), static fn (array $row): bool => ! $row['ran']))
                : 0;

            $pending === 0
                ? $this->ok('Migraciones al día')
                : $this->warning("Hay {$pending} migración(es) pendiente(s): ejecuta php bin/bootzen migrate.");
        } catch (Throwable $e) {
            $this->warning('No se pudo revisar las migraciones: ' . $e->getMessage());
        }
    }

    private function checkMail(): void
    {
        $config = $this->app->config();
        $driver = $config->get('mail.driver', 'log');
        $production = $config->get('app.env') === 'production';

        if ($driver !== 'smtp') {
            if ($production && $driver === 'log') {
                $this->warning('MAIL_DRIVER=log en producción: los correos no salen, quedan en storage/logs. Usa smtp.');
            }

            return;
        }

        $from = $config->get('mail.from.address', '');
        $host = $config->get('mail.smtp.host', '');
        $encryption = $config->get('mail.smtp.encryption', 'tls');
        $problems = [];

        if (! is_string($from) || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $problems[] = 'MAIL_FROM_ADDRESS no es un correo válido';
        }

        if (! is_string($host) || $host === '') {
            $problems[] = 'falta MAIL_HOST';
        }

        if ($encryption !== 'none' && ! extension_loaded('openssl')) {
            $problems[] = 'falta la extensión openssl para MAIL_ENCRYPTION=' . (is_string($encryption) ? $encryption : 'tls');
        }

        if ($problems !== []) {
            $this->error('Correo SMTP: ' . implode(', ', $problems) . '.');

            return;
        }

        $this->ok('Correo por SMTP en ' . $host . ' (pruébalo con php bin/bootzen mail:test tu@correo.com)');

        if ($production && (str_ends_with((string) $from, '@example.com') || in_array($host, ['127.0.0.1', 'localhost'], true))) {
            $this->warning('MAIL_FROM_ADDRESS o MAIL_HOST siguen con valores de ejemplo.');
        }

        if (filter_var($config->get('mail.smtp.verify_peer', true), FILTER_VALIDATE_BOOL) === false) {
            $this->warning('MAIL_VERIFY_PEER=false: el certificado del servidor SMTP no se verifica.');
        }
    }

    private function checkStorage(): void
    {
        $manager = $this->app->make(StorageManager::class);

        if (! $manager instanceof StorageManager) {
            return;
        }

        foreach (['privados' => fn (): Storage => $manager->disk(), 'públicos' => fn (): Storage => $manager->publicDisk()] as $label => $resolve) {
            try {
                $storage = $resolve();
                $disk = $storage->disk();

                // Una consulta real: en discos remotos prueba la conexión y las credenciales
                $storage->exists('.bootzen-doctor');

                if ($disk instanceof LocalDisk) {
                    $this->ok("Archivos {$label}: disco {$storage->name} (local)");
                } else {
                    $this->ok("Archivos {$label}: disco {$storage->name} (" . strtolower((string) preg_replace('/Disk$/', '', (new \ReflectionClass($disk))->getShortName())) . '), conexión correcta');
                }
            } catch (Throwable $e) {
                $this->error("Archivos {$label}: " . $e->getMessage());
            }
        }

        if (! extension_loaded('gd')) {
            $this->warning('Falta la extensión gd: resize() y cover() de imágenes no funcionarán.');
        }
    }

    private function checkQueue(): void
    {
        if ($this->app->config()->get('queue.driver', 'sync') !== 'database') {
            return;
        }

        try {
            $queue = $this->app->make(DatabaseQueue::class);
            $failed = $queue instanceof DatabaseQueue ? count($queue->failed()) : 0;
            $pending = $queue instanceof DatabaseQueue ? $queue->size() : 0;
        } catch (Throwable $e) {
            $this->error('Cola: ' . $e->getMessage());

            return;
        }

        $this->ok("Cola en la base: {$pending} pendiente(s)");

        if ($failed > 0) {
            $this->warning("Hay {$failed} job(s) fallidos: revísalos con php bin/bootzen queue:failed.");
        }
    }

    private function checkSchedule(): void
    {
        try {
            $tasks = $this->schedule === null ? [] : ($this->schedule)()->tasks();
        } catch (Throwable $e) {
            $this->error('routes/schedule.php: ' . $e->getMessage());

            return;
        }

        if ($tasks === []) {
            return;
        }

        $file = $this->app->basePath('storage/cache/schedule.json');
        $state = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $last = is_array($state) && is_int($state['last_run'] ?? null) ? $state['last_run'] : null;
        $cron = Console::cronLine($this->app->basePath());

        if ($last === null) {
            $this->warning(count($tasks) . " tarea(s) programada(s), pero schedule:run nunca corrió. Agrega al cron: {$cron}");
        } elseif (time() - $last > 20 * 60) {
            $this->warning('schedule:run no corre desde hace ' . intdiv(time() - $last, 60) . " minutos. Revisa el cron: {$cron}");
        } else {
            $this->ok(count($tasks) . ' tarea(s) programada(s); schedule:run corrió hace ' . intdiv(time() - $last, 60) . ' min');
        }
    }

    private function checkAssets(): void
    {
        is_file($this->app->basePath('public/assets/app.css'))
            ? $this->ok('CSS compilado en public/assets/app.css')
            : $this->error('Falta public/assets/app.css: ejecuta php bin/bootzen css:build.');
    }

    /**
     * Carpeta de la base SQLite (relativa al proyecto si está dentro), o null si no es SQLite en archivo.
     */
    private function sqliteDirectory(): ?string
    {
        if ($this->driver() !== 'sqlite') {
            return null;
        }

        $default = $this->app->config()->get('database.default', 'sqlite');
        $file = $this->app->config()->get('database.connections.' . (is_string($default) ? $default : 'sqlite') . '.database', 'database/app.sqlite');
        $file = is_string($file) && $file !== '' ? $file : 'database/app.sqlite';

        if ($file === ':memory:') {
            return null;
        }

        $directory = dirname($file);
        $base = rtrim($this->app->basePath(), '/') . '/';

        return str_starts_with($directory . '/', $base) ? substr($directory, strlen($base)) : $directory;
    }

    private function driver(): string
    {
        $default = $this->app->config()->get('database.default', 'sqlite');
        $driver = is_string($default) ? $this->app->config()->get("database.connections.{$default}.driver", 'sqlite') : 'sqlite';

        return is_string($driver) ? $driver : 'sqlite';
    }

    private function ok(string $message): void
    {
        ($this->line)("  ✓ {$message}");
    }

    private function warning(string $message): void
    {
        ($this->line)("  ! {$message}");
    }

    private function error(string $message): void
    {
        $this->errors++;
        ($this->line)("  ✗ {$message}");
    }
}
