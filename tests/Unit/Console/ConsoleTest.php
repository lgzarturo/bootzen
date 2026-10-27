<?php

declare(strict_types=1);

use BootZen\Console\Console;
use BootZen\Core\Application;
use BootZen\Frontend\Tailwind;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-cli');
    writeFile($this->project . '/resources/views/inicio.php', '<h1>{{ $x }}</h1>');
    writeFile($this->project . '/resources/css/app.css', '@import "tailwindcss";');
    $this->app = new Application($this->project);
    $this->output = fopen('php://memory', 'w+');
    $this->commands = [];
    $this->stopped = 0;

    $this->console = function (?Tailwind $tailwind = null): Console {
        return new Console(
            $this->app,
            $tailwind ?? new Tailwind($this->project, 'Linux', 'x86_64', false, static function (string $url, string $destination): void {
                file_put_contents($destination, str_ends_with($url, '.txt') ? hash('sha256', 'bin') . "  ./tailwindcss-linux-x64\n" : 'bin');
            }),
            $this->output,
            function (array $command): int {
                $this->commands[] = $command;

                return 0;
            },
            function (array $command): Closure {
                $this->commands[] = $command;

                return function (): void {
                    $this->stopped++;
                };
            }
        );
    };
});

afterEach(function (): void {
    removeDir($this->project);
});

function consoleOutput($stream): string
{
    rewind($stream);

    return (string) stream_get_contents($stream);
}

it('lista los comandos y rechaza los desconocidos', function (): void {
    expect(($this->console)()->run(['bootzen']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('css:build')
        ->and(($this->console)()->run(['bootzen', 'nada']))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('Comando desconocido: nada');
});

it('instala Tailwind si falta y compila el CSS', function (): void {
    expect(($this->console)()->run(['bootzen', 'css:build']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('Descargando Tailwind')
        ->and($this->commands[0])->toContain('--minify')
        ->and(is_file($this->project . '/storage/bin/tailwindcss-' . Tailwind::VERSION))->toBeTrue();

    ($this->console)()->run(['bootzen', 'css:watch']);

    expect($this->commands[1])->toContain('--watch')
        ->and(substr_count(consoleOutput($this->output), 'Descargando'))->toBe(1);
});

it('levanta el servidor con Tailwind en segundo plano y lo detiene al salir', function (): void {
    expect(($this->console)()->run(['bootzen', 'serve', '--port=9000', '--host=0.0.0.0']))->toBe(0)
        ->and($this->commands[0])->toContain('--watch')
        ->and($this->commands[1])->toBe([PHP_BINARY, '-S', '0.0.0.0:9000', '-t', $this->project . '/public'])
        ->and($this->stopped)->toBe(1);
});

it('levanta solo el servidor con --no-css', function (): void {
    ($this->console)()->run(['bootzen', 'serve', '--no-css']);

    expect($this->commands)->toHaveCount(1)
        ->and($this->commands[0][2])->toBe('127.0.0.1:8000');
});

it('precompila y limpia las vistas', function (): void {
    expect(($this->console)()->run(['bootzen', 'view:cache']))->toBe(0)
        ->and(glob($this->project . '/storage/cache/views/*.php'))->toHaveCount(1)
        ->and(($this->console)()->run(['bootzen', 'view:clear']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('Vistas compiladas borradas: 1');
});

it('muestra el error y devuelve 1 si un comando falla', function (): void {
    writeFile($this->project . '/resources/views/rota.php', '@if ($x)');

    expect(($this->console)()->run(['bootzen', 'view:cache']))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('Error: Error de sintaxis en la vista');
});
