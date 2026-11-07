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

it('migra, muestra el estado, revierte y vuelve a crear la base', function (): void {
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear_tareas.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Migration;

        return new class () extends Migration {
            public function up(Connection $db): void { $db->exec('CREATE TABLE tareas (id INTEGER PRIMARY KEY, titulo TEXT)'); }
            public function down(Connection $db): void { $db->exec('DROP TABLE tareas'); }
        };
        PHP);
    writeFile($this->project . '/database/seeds/DatabaseSeeder.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Seeder;

        return new class () extends Seeder {
            public function run(Connection $db): void { $db->table('tareas')->insert(['titulo' => 'Pan']); }
        };
        PHP);
    $console = ($this->console)();

    expect($console->run(['bootzen', 'migrate:status']))->toBe(0)
        ->and($console->run(['bootzen', 'migrate']))->toBe(0)
        ->and($console->run(['bootzen', 'migrate']))->toBe(0)
        ->and($console->run(['bootzen', 'migrate:status']))->toBe(0)
        ->and($console->run(['bootzen', 'db:seed']))->toBe(0)
        ->and($console->run(['bootzen', 'migrate:rollback', '--step=2']))->toBe(0)
        ->and($console->run(['bootzen', 'migrate:rollback']))->toBe(0)
        ->and($console->run(['bootzen', 'db:fresh', '--seed']))->toBe(0)
        ->and(db()->table('tareas')->count())->toBe(1);

    $output = consoleOutput($this->output);

    expect($output)->toContain('[ ] 2026_01_01_000000_crear_tareas')
        ->and($output)->toContain('Migrada: 2026_01_01_000000_crear_tareas')
        ->and($output)->toContain('No hay migraciones pendientes.')
        ->and($output)->toContain('[x] 2026_01_01_000000_crear_tareas (lote 1)')
        ->and($output)->toContain('Seeder ejecutado: DatabaseSeeder')
        ->and($output)->toContain('Revertida: 2026_01_01_000000_crear_tareas')
        ->and($output)->toContain('No hay migraciones para revertir.')
        ->and($output)->toContain('Tablas borradas.');
});

it('pide --force para db:fresh en producción', function (): void {
    writeFile($this->project . '/config/app.php', "<?php return ['env' => 'production'];");
    $this->app = new Application($this->project);

    expect(($this->console)()->run(['bootzen', 'db:fresh']))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('agrega --force')
        ->and(($this->console)()->run(['bootzen', 'db:fresh', '--force']))->toBe(0);
});

it('crea una copia consistente de la base SQLite', function (): void {
    db()->exec('CREATE TABLE notas (texto TEXT)');
    db()->table('notas')->insert(['texto' => 'hola']);

    expect(($this->console)()->run(['bootzen', 'db:backup']))->toBe(0);

    $backups = glob($this->project . '/storage/backups/backup-*.sqlite') ?: [];
    $copy = new PDO('sqlite:' . ($backups[0] ?? ''));

    expect($backups)->toHaveCount(1)
        ->and($copy->query('SELECT texto FROM notas')?->fetchColumn())->toBe('hola');
});

it('rechaza db:backup en bases que no son SQLite', function (): void {
    $this->app->instance(BootZen\Database\Connection::class, new BootZen\Database\Connection(new PDO('sqlite::memory:'), 'mysql'));

    expect(($this->console)()->run(['bootzen', 'db:backup']))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('mysqldump');
});

it('arma el artefacto de despliegue compilando antes el CSS', function (): void {
    writeFile($this->project . '/config/app.php', "<?php return ['name' => 'Mi Tienda'];");
    writeFile($this->project . '/public/index.php', '<?php');
    writeFile($this->project . '/.env', 'APP_KEY=secreta');
    $this->app = new Application($this->project);

    expect(($this->console)()->run(['bootzen', 'deploy:build', '--version=1.2.0']))->toBe(0)
        ->and($this->commands[0])->toContain('--minify')
        ->and(consoleOutput($this->output))->toContain('vistas precompiladas')
        ->toContain('Carpeta: build/mi-tienda-1.2.0')
        ->toContain('Archivo: build/mi-tienda-1.2.0.zip')
        ->toContain('php bin/bootzen migrate')
        ->and($this->project . '/build/mi-tienda-1.2.0/public/index.php')->toBeFile()
        ->and($this->project . '/build/mi-tienda-1.2.0/.env')->not->toBeFile();
});

it('arma el artefacto sin CSS y con una versión por fecha', function (): void {
    expect(($this->console)()->run(['bootzen', 'deploy:build', '--no-css']))->toBe(0)
        ->and($this->commands)->toBe([])
        ->and(consoleOutput($this->output))->toMatch('#Carpeta: build/[a-z0-9-]+-\d{14}#');
});

it('no arma el artefacto si falla el CSS', function (): void {
    $console = new Console($this->app, new Tailwind($this->project, 'Linux', 'x86_64', false, static function (string $url, string $destination): void {
        file_put_contents($destination, str_ends_with($url, '.txt') ? hash('sha256', 'bin') . "  ./tailwindcss-linux-x64\n" : 'bin');
    }), $this->output, static fn (array $command): int => 1);

    expect($console->run(['bootzen', 'deploy:build']))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('Usa --no-css')
        ->and($this->project . '/build')->not->toBeDirectory();
});

it('actualiza framework/ desde la instalación local', function (): void {
    $home = makeTempDir('bootzen-home');
    writeFile($home . '/VERSION', "2.1.0\n");
    writeFile($home . '/framework/bootstrap.php', '<?php // 2.1.0');
    writeFile($this->project . '/framework/bootstrap.php', '<?php // 2.0.0');
    writeFile($this->project . '/framework/VERSION', "2.0.0\n");
    BootZen\Deploy\FrameworkUpdater::writeManifest($this->project . '/framework', '2.0.0');
    putenv('BOOTZEN_HOME=' . $home);

    try {
        expect(($this->console)()->run(['bootzen', 'framework:update']))->toBe(0)
            ->and(consoleOutput($this->output))->toContain('framework/ actualizado de 2.0.0 a 2.1.0. Respaldo en storage/backups/framework-2.0.0-')
            ->and(($this->console)()->run(['bootzen', 'framework:update', '--from=' . $home]))->toBe(0)
            ->and(consoleOutput($this->output))->toContain('ya está en la versión 2.1.0')
            ->and(($this->console)()->run(['bootzen', 'framework:update', '--from=' . $home . '/nada']))->toBe(1)
            ->and(consoleOutput($this->output))->toContain('No hay una instalación de BootZen');
    } finally {
        putenv('BOOTZEN_HOME');
        removeDir($home);
    }
});
