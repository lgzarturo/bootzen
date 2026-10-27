<?php

/**
 * Console.php
 *
 * CLI del proyecto (bin/bootzen). Comandos de la fase 2: servidor de desarrollo, Tailwind y caché de vistas.
 * La fase 5 agrega generadores, migraciones y el resto de comandos.
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

        try {
            return match ($command) {
                'help', 'list', '--help', '-h' => $this->help(),
                'serve' => $this->serve($options),
                'css:install' => $this->cssInstall(),
                'css:build' => $this->cssBuild(false),
                'css:watch' => $this->cssBuild(true),
                'view:cache' => $this->viewCache(),
                'view:clear' => $this->viewClear(),
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
            $this->line(sprintf('  %-12s %s', $name, $description));
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
