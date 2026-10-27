<?php

/**
 * Console.php
 *
 * CLI del proyecto (bin/bootzen): servidor de desarrollo, Tailwind, vistas, base de datos, generadores
 * (make:*), rutas, configuración, diagnóstico (doctor) y verificación (check).
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
use BootZen\Core\Config;
use BootZen\Core\Route;
use BootZen\Database\Connection;
use BootZen\Database\Migrator;
use BootZen\Database\SeederRunner;
use BootZen\Frontend\Tailwind;
use BootZen\View\View;
use Closure;
use Throwable;

/**
 * Despachador de comandos de bin/bootzen.
 *
 * @example php bin/bootzen css:build
 */
final class Console
{
    /**
     * Comandos disponibles => descripción.
     */
    private const COMMANDS = [
        'serve' => 'Servidor de desarrollo + Tailwind en modo watch (--port=8000 --host=127.0.0.1 --no-css)',
        'css:install' => 'Descarga el binario de Tailwind (verificado con SHA-256) en storage/bin',
        'css:build' => 'Compila resources/css/app.css a public/assets/app.css (minificado)',
        'css:watch' => 'Recompila el CSS cada vez que cambian las vistas o el JS',
        'view:cache' => 'Precompila todas las vistas (para producción)',
        'view:clear' => 'Borra las vistas compiladas',
        'migrate' => 'Ejecuta las migraciones pendientes de database/migrations',
        'migrate:rollback' => 'Revierte el último lote de migraciones (--step=N para más lotes)',
        'migrate:status' => 'Lista las migraciones y si ya se ejecutaron',
        'db:seed' => 'Ejecuta database/seeds/DatabaseSeeder.php (--class=Nombre para otro)',
        'db:fresh' => 'Borra todas las tablas y migra de nuevo (--seed, --force en producción)',
        'db:backup' => 'Copia consistente de la base SQLite en storage/backups',
        'make:resource' => 'CRUD completo: make:resource Task title:string done:bool (--table=nombre)',
        'make:controller' => 'Controlador con su vista: make:controller Factura',
        'make:model' => 'Modelo: make:model Factura (--migration crea su tabla, --table=nombre)',
        'make:migration' => 'Migración: make:migration crear_facturas (trae el CREATE TABLE)',
        'make:middleware' => 'Middleware en app/Middleware',
        'make:seeder' => 'Seeder en database/seeds',
        'make:test' => 'Test de feature en tests/Feature (--unit para tests/Unit)',
        'make:component' => 'Componente <x-nombre> en resources/views/components (--class si tiene lógica)',
        'route:list' => 'Lista las rutas: método, path, nombre, middleware y acción',
        'config:cache' => 'Guarda config/ en un solo archivo para producción',
        'config:clear' => 'Borra la caché de configuración',
        'key:generate' => 'Genera APP_KEY en .env (--show solo la muestra, --force la reemplaza)',
        'doctor' => 'Revisa PHP, extensiones, permisos, .env y base de datos',
        'check' => 'Formato, PHPStan y tests, como composer check',
    ];

    /**
     * Ejecuta un proceso en primer plano y devuelve su código de salida.
     *
     * @var Closure(list<string>): int
     */
    private readonly Closure $runner;

    /**
     * Inicia un proceso en segundo plano y devuelve una función que lo detiene.
     *
     * @var Closure(list<string>): (Closure(): void)
     */
    private readonly Closure $background;

    /**
     * @param resource $output Salida de mensajes
     * @param (Closure(list<string>): int)|null $runner
     * @param (Closure(list<string>): (Closure(): void))|null $background
     */
    public function __construct(
        private readonly Application $app,
        private readonly Tailwind $tailwind,
        private $output = STDOUT,
        ?Closure $runner = null,
        ?Closure $background = null
    ) {
        $this->runner = $runner ?? self::runProcess(...);
        $this->background = $background ?? self::startProcess(...);
    }

    /**
     * Ejecuta el comando indicado en $argv y devuelve el código de salida.
     *
     * @param list<string> $argv Argumentos tal como llegan a bin/bootzen
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $options = $this->options(array_slice($argv, 2));
        $arguments = array_values(array_filter(array_slice($argv, 2), static fn (string $argument): bool => ! str_starts_with($argument, '--')));

        try {
            return match ($command) {
                'help', 'list', '--help', '-h' => $this->help(),
                'serve' => $this->serve($options),
                'css:install' => $this->cssInstall(),
                'css:build' => $this->cssBuild(false),
                'css:watch' => $this->cssBuild(true),
                'view:cache' => $this->viewCache(),
                'view:clear' => $this->viewClear(),
                'migrate' => $this->migrate(),
                'migrate:rollback' => $this->rollback($options),
                'migrate:status' => $this->migrateStatus(),
                'db:seed' => $this->seed($options),
                'db:fresh' => $this->fresh($options),
                'db:backup' => $this->backup(),
                'make:resource' => $this->make(fn (Generator $g): array => $g->resource(
                    $this->argument($arguments, 0, 'make:resource Task title:string done:bool'),
                    array_slice($arguments, 1),
                    $this->stringOption($options, 'table')
                ), 'Siguiente: php bin/bootzen migrate y composer check.'),
                'make:controller' => $this->make(fn (Generator $g): array => $g->controller($this->argument($arguments, 0, 'make:controller Factura'))),
                'make:model' => $this->make(fn (Generator $g): array => $g->model(
                    $this->argument($arguments, 0, 'make:model Factura'),
                    $this->stringOption($options, 'table'),
                    isset($options['migration'])
                )),
                'make:migration' => $this->make(fn (Generator $g): array => $g->migration($this->argument($arguments, 0, 'make:migration crear_facturas'))),
                'make:middleware' => $this->make(fn (Generator $g): array => $g->middleware($this->argument($arguments, 0, 'make:middleware SoloAdmins'))),
                'make:seeder' => $this->make(fn (Generator $g): array => $g->seeder($this->argument($arguments, 0, 'make:seeder ClientesSeeder'))),
                'make:test' => $this->make(fn (Generator $g): array => $g->test($this->argument($arguments, 0, 'make:test FacturaTest'), isset($options['unit']))),
                'make:component' => $this->make(fn (Generator $g): array => $g->component(
                    $this->argument($arguments, 0, 'make:component modal'),
                    isset($options['class'])
                )),
                'route:list' => $this->routeList(),
                'config:cache' => $this->configCache(),
                'config:clear' => $this->configClear(),
                'key:generate' => $this->keyGenerate($options),
                'doctor' => (new Doctor($this->app, $this->line(...)))->run(),
                'check' => $this->check(),
                default => $this->unknown($command),
            };
        } catch (Throwable $e) {
            $this->line("Error: {$e->getMessage()}");

            return 1;
        }
    }

    private function help(): int
    {
        $this->line('BootZen: comandos disponibles');
        $this->line('');

        foreach (self::COMMANDS as $name => $description) {
            $this->line(sprintf('  %-17s %s', $name, $description));
        }

        $this->line('');
        $this->line('Uso: php bin/bootzen <comando>');

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function serve(array $options): int
    {
        $host = is_string($options['host'] ?? null) ? $options['host'] : '127.0.0.1';
        $port = is_string($options['port'] ?? null) ? $options['port'] : '8000';
        $stopCss = null;

        if (! isset($options['no-css']) && is_file($this->app->basePath('resources/css/app.css'))) {
            $this->ensureTailwind();
            $stopCss = ($this->background)($this->tailwind->buildArguments(true));
        }

        $this->line("Servidor en http://{$host}:{$port} (Ctrl+C para detener)");

        try {
            return ($this->runner)([PHP_BINARY, '-S', "{$host}:{$port}", '-t', $this->app->basePath('public')]);
        } finally {
            if ($stopCss !== null) {
                $stopCss();
            }
        }
    }

    private function cssInstall(): int
    {
        $this->line('Descargando Tailwind ' . Tailwind::VERSION . " ({$this->tailwind->assetName()})...");
        $path = $this->tailwind->install();
        $this->line("Listo: {$path}");

        return 0;
    }

    private function cssBuild(bool $watch): int
    {
        $this->ensureTailwind();

        return ($this->runner)($this->tailwind->buildArguments($watch));
    }

    private function viewCache(): int
    {
        $count = $this->view()->compileAll();
        $this->line("Vistas compiladas: {$count}");

        return 0;
    }

    private function viewClear(): int
    {
        $count = $this->view()->clearCache();
        $this->line("Vistas compiladas borradas: {$count}");

        return 0;
    }

    private function migrate(): int
    {
        $ran = $this->migrator()->run();

        if ($ran === []) {
            $this->line('No hay migraciones pendientes.');
        }

        foreach ($ran as $name) {
            $this->line("Migrada: {$name}");
        }

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function rollback(array $options): int
    {
        $steps = is_string($options['step'] ?? null) ? max(1, (int) $options['step']) : 1;
        $reverted = $this->migrator()->rollback($steps);

        if ($reverted === []) {
            $this->line('No hay migraciones para revertir.');
        }

        foreach ($reverted as $name) {
            $this->line("Revertida: {$name}");
        }

        return 0;
    }

    private function migrateStatus(): int
    {
        $status = $this->migrator()->status();

        if ($status === []) {
            $this->line('No hay migraciones en database/migrations.');
        }

        foreach ($status as $row) {
            $this->line(sprintf('  [%s] %s%s', $row['ran'] ? 'x' : ' ', $row['migration'], $row['ran'] ? " (lote {$row['batch']})" : ''));
        }

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function seed(array $options): int
    {
        $name = is_string($options['class'] ?? null) ? $options['class'] : 'DatabaseSeeder';
        $runner = $this->app->make(SeederRunner::class);

        if (! $runner instanceof SeederRunner) {
            throw new \LogicException('El servicio SeederRunner debe ser una instancia de ' . SeederRunner::class . '.');
        }

        foreach ($runner->run($name) as $seeder) {
            $this->line("Seeder ejecutado: {$seeder}");
        }

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function fresh(array $options): int
    {
        if ($this->app->config()->get('app.env') === 'production' && ! isset($options['force'])) {
            $this->line('db:fresh borra todas las tablas. En producción agrega --force si de verdad quieres hacerlo.');

            return 1;
        }

        $this->line('Tablas borradas.');
        foreach ($this->migrator()->fresh() as $name) {
            $this->line("Migrada: {$name}");
        }

        return isset($options['seed']) ? $this->seed([]) : 0;
    }

    private function backup(): int
    {
        $db = $this->connection();

        if ($db->driver() !== 'sqlite') {
            $this->line('db:backup solo funciona con SQLite. Para MySQL usa mysqldump.');

            return 1;
        }

        $directory = $this->app->basePath('storage/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $file = $directory . '/backup-' . date('Ymd-His') . '.sqlite';
        // VACUUM INTO genera una copia consistente aunque la app esté escribiendo
        $db->statement('VACUUM INTO ?', [$file]);
        $this->line("Copia creada: {$file}");

        return 0;
    }

    /**
     * Ejecuta un generador y lista los archivos creados.
     *
     * @param Closure(Generator): list<string> $generate
     */
    private function make(Closure $generate, string $next = ''): int
    {
        $connections = $this->app->config()->get('database.connections', []);
        $default = $this->app->config()->get('database.default', 'sqlite');
        $connection = is_array($connections) && is_string($default) ? ($connections[$default] ?? []) : [];
        $driver = is_array($connection) && is_string($connection['driver'] ?? null) ? $connection['driver'] : 'sqlite';

        $this->line('Archivos:');
        foreach ($generate(new Generator($this->app->basePath(), $driver)) as $file) {
            $this->line("  {$file}");
        }

        if ($next !== '') {
            $this->line($next);
        }

        return 0;
    }

    private function routeList(): int
    {
        $rows = [['Método', 'Path', 'Nombre', 'Middleware', 'Acción']];

        foreach ($this->app->getRouter()->getRoutes() as $route) {
            $rows[] = [
                implode('|', $route->getMethods()),
                $route->getPath(),
                $route->getName() ?? '',
                implode(', ', array_map(
                    static fn (mixed $middleware): string => is_string($middleware) ? $middleware : $middleware::class,
                    $route->getMiddleware()
                )),
                self::describeHandler($route),
            ];
        }

        if (count($rows) === 1) {
            $this->line('No hay rutas registradas.');

            return 0;
        }

        $widths = [];
        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($cell));
            }
        }

        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $index => $cell) {
                $cells[] = $cell . str_repeat(' ', $widths[$index] - mb_strlen($cell));
            }

            $this->line(rtrim('  ' . implode('  ', $cells)));
        }

        return 0;
    }

    private static function describeHandler(Route $route): string
    {
        $handler = $route->getHandler();

        if (is_array($handler) && count($handler) === 2 && is_string($handler[1])) {
            $class = is_object($handler[0]) ? $handler[0]::class : (string) $handler[0];

            return substr($class, (int) strrpos('\\' . $class, '\\')) . '@' . $handler[1];
        }

        return $handler instanceof Closure ? 'Closure' : (is_string($handler) ? $handler : get_debug_type($handler));
    }

    private function configCache(): int
    {
        $file = $this->app->basePath('storage/cache/config.php');
        Config::fromDirectory($this->app->basePath('config'))->writeCache($file);
        $this->line('Configuración guardada en storage/cache/config.php. Tras cambiar config/ o .env, vuelve a ejecutar config:cache.');

        return 0;
    }

    private function configClear(): int
    {
        $file = $this->app->basePath('storage/cache/config.php');

        if (is_file($file)) {
            unlink($file);
        }

        $this->line('Caché de configuración borrada.');

        return 0;
    }

    /**
     * @param array<string, string|true> $options
     */
    private function keyGenerate(array $options): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32));

        if (isset($options['show'])) {
            $this->line($key);

            return 0;
        }

        $env = $this->app->basePath('.env');
        $example = $this->app->basePath('.env.example');
        $contents = is_file($env) ? (string) file_get_contents($env) : (is_file($example) ? (string) file_get_contents($example) : '');

        if (preg_match('/^APP_KEY=(.+)$/m', $contents, $match) === 1 && trim($match[1]) !== '' && ! isset($options['force'])) {
            $this->line('Ya hay una APP_KEY en .env. Usa --force para reemplazarla.');

            return 1;
        }

        $contents = preg_match('/^APP_KEY=.*$/m', $contents) === 1
            ? (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $contents)
            : rtrim($contents) . ($contents === '' ? '' : "\n") . 'APP_KEY=' . $key . "\n";

        file_put_contents($env, $contents);
        $this->line('APP_KEY guardada en .env.');

        return 0;
    }

    /**
     * Formato, PHPStan y tests con las herramientas de vendor/ (necesita composer install).
     */
    private function check(): int
    {
        $bin = $this->app->basePath('vendor/bin');

        if (! is_dir($bin)) {
            $this->line('Falta vendor/: ejecuta composer install para instalar las herramientas de desarrollo.');

            return 1;
        }

        $steps = [
            'Formato (PHP CS Fixer)' => [PHP_BINARY, $bin . '/php-cs-fixer', 'fix', '--dry-run', '--diff'],
            'Análisis estático (PHPStan)' => [PHP_BINARY, $bin . '/phpstan', 'analyse', '--memory-limit=512M', '--no-progress'],
            'Tests (Pest)' => [PHP_BINARY, $bin . '/pest'],
        ];

        $cwd = getcwd();
        chdir($this->app->basePath());

        try {
            foreach ($steps as $title => $command) {
                $this->line("> {$title}");
                $status = ($this->runner)($command);

                if ($status !== 0) {
                    $this->line("Falló: {$title}. Corrige lo que indica arriba y vuelve a ejecutar php bin/bootzen check.");

                    return $status;
                }
            }
        } finally {
            if (is_string($cwd)) {
                chdir($cwd);
            }
        }

        $this->line('Todo en verde.');

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @throws \InvalidArgumentException Si falta el argumento
     */
    private function argument(array $arguments, int $index, string $usage): string
    {
        return $arguments[$index] ?? throw new \InvalidArgumentException("Falta el nombre. Uso: php bin/bootzen {$usage}");
    }

    /**
     * @param array<string, string|true> $options
     */
    private function stringOption(array $options, string $name): ?string
    {
        return is_string($options[$name] ?? null) ? $options[$name] : null;
    }

    private function migrator(): Migrator
    {
        $migrator = $this->app->make(Migrator::class);

        if (! $migrator instanceof Migrator) {
            throw new \LogicException('El servicio Migrator debe ser una instancia de ' . Migrator::class . '.');
        }

        return $migrator;
    }

    private function connection(): Connection
    {
        $connection = $this->app->make(Connection::class);

        if (! $connection instanceof Connection) {
            throw new \LogicException('El servicio Connection debe ser una instancia de ' . Connection::class . '.');
        }

        return $connection;
    }

    private function unknown(string $command): int
    {
        $this->line("Comando desconocido: {$command}");
        $this->help();

        return 1;
    }

    private function ensureTailwind(): void
    {
        if (! $this->tailwind->isInstalled()) {
            $this->cssInstall();
        }
    }

    private function view(): View
    {
        $view = $this->app->make(View::class);

        if (! $view instanceof View) {
            throw new \LogicException('El servicio View debe ser una instancia de ' . View::class . '.');
        }

        return $view;
    }

    /**
     * Convierte --clave=valor y --bandera en un array.
     *
     * @param list<string> $arguments
     * @return array<string, string|true>
     */
    private function options(array $arguments): array
    {
        $options = [];

        foreach ($arguments as $argument) {
            if (preg_match('/^--([\w-]+)(?:=(.*))?$/', $argument, $match) === 1) {
                $options[$match[1]] = $match[2] ?? true;
            }
        }

        return $options;
    }

    private function line(string $message): void
    {
        fwrite($this->output, $message . PHP_EOL);
    }

    /**
     * Ejecuta un proceso heredando la entrada y salida de la terminal.
     *
     * @param list<string> $command
     */
    private static function runProcess(array $command): int
    {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);

        return is_resource($process) ? proc_close($process) : 1;
    }

    /**
     * Inicia un proceso en segundo plano y devuelve una función para detenerlo.
     *
     * @param list<string> $command
     * @return Closure(): void
     */
    private static function startProcess(array $command): Closure
    {
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);

        return static function () use ($process): void {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        };
    }
}
