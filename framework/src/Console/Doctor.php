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
     */
    public function __construct(private readonly Application $app, private readonly Closure $line)
    {
    }

    public function run(): int
    {
        ($this->line)('Diagnóstico de ' . $this->app->config()->get('app.name', 'la aplicación'));

        $this->checkPhp();
        $this->checkEnvironment();
        $this->checkPermissions();
        $this->checkDatabase();
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
        is_file($this->app->basePath('.env'))
            ? $this->ok('.env presente')
            : $this->warning('No hay .env: copia .env.example a .env (o define las variables en el servidor).');

        $key = $this->app->config()->get('app.key');
        is_string($key) && $key !== ''
            ? $this->ok('APP_KEY definida')
            : $this->warning('APP_KEY vacía: ejecuta php bin/bootzen key:generate.');

        $production = $this->app->config()->get('app.env') === 'production';

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

        if ($this->driver() === 'sqlite') {
            $directories[] = 'database';
        }

        $problems = [];
        foreach ($directories as $directory) {
            // Si la carpeta no existe todavía, basta con poder crearla en la primera carpeta que sí existe
            $path = $this->app->basePath($directory);
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

    private function checkAssets(): void
    {
        is_file($this->app->basePath('public/assets/app.css'))
            ? $this->ok('CSS compilado en public/assets/app.css')
            : $this->error('Falta public/assets/app.css: ejecuta php bin/bootzen css:build.');
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
