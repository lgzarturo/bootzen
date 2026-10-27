<?php

declare(strict_types=1);

use BootZen\Console\Console;
use BootZen\Core\Application;
use BootZen\Core\Router;
use BootZen\Frontend\Tailwind;

final class ConsoleToolsTestController
{
    public function index(): string
    {
        return 'ok';
    }
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-tools');
    writeFile($this->project . '/config/app.php', "<?php return ['name' => 'Tienda', 'env' => env('BZ_TOOLS_ENV', 'local'), 'debug' => env('BZ_TOOLS_DEBUG', false), 'key' => env('APP_KEY')];");
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => 'database/app.sqlite']]];");
    writeFile($this->project . '/routes/web.php', (string) file_get_contents(dirname(__DIR__, 3) . '/skeleton/routes/web.php'));
    $this->output = fopen('php://memory', 'w+');
    $this->commands = [];
    $this->status = 0;

    $this->run = function (string ...$argv): int {
        $app = new Application($this->project);
        $app->middlewareGroup('web', []);
        $app->routes(static function (Router $router): void {
            $router->group(['middleware' => 'web'], static function (Router $router): void {
                $router->get('/', [ConsoleToolsTestController::class, 'index'])->name('home');
                $router->get('/up', static fn (): array => ['status' => 'ok']);
            });
        });

        $console = new Console($app, new Tailwind($this->project, 'Linux', 'x86_64'), $this->output, function (array $command): int {
            $this->commands[] = $command;

            return $this->status;
        });

        return $console->run(['bootzen', ...$argv]);
    };
});

afterEach(function (): void {
    forgetEnv('BZ_TOOLS_ENV', 'BZ_TOOLS_DEBUG', 'APP_KEY', 'APP_NAME');
    removeDir($this->project);
});

it('lista las rutas en columnas', function (): void {
    expect(($this->run)('route:list'))->toBe(0);

    $output = consoleOutput($this->output);

    expect($output)->toMatch('/Método\s+Path\s+Nombre\s+Middleware\s+Acción/')
        ->toMatch('#GET\s+/\s+home\s+web\s+ConsoleToolsTestController@index#')
        ->toMatch('#/up\s+web\s+Closure#');
});

it('guarda y borra la caché de configuración', function (): void {
    expect(($this->run)('config:cache'))->toBe(0)
        ->and(require $this->project . '/storage/cache/config.php')->toMatchArray(['app' => ['name' => 'Tienda', 'env' => 'local', 'debug' => false, 'key' => null]]);

    // Con la caché, un cambio en config/ no se ve hasta regenerarla
    writeFile($this->project . '/config/app.php', "<?php return ['name' => 'Otra'];");
    expect((new Application($this->project))->config()->get('app.name'))->toBe('Tienda');

    expect(($this->run)('config:clear'))->toBe(0)
        ->and(is_file($this->project . '/storage/cache/config.php'))->toBeFalse()
        ->and((new Application($this->project))->config()->get('app.name'))->toBe('Otra');
});

it('genera APP_KEY en .env y no la reemplaza sin --force', function (): void {
    writeFile($this->project . '/.env.example', "APP_NAME=Tienda\nAPP_KEY=\n");

    expect(($this->run)('key:generate'))->toBe(0)
        ->and((string) file_get_contents($this->project . '/.env'))->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m')->toContain('APP_NAME=Tienda');

    $first = (string) file_get_contents($this->project . '/.env');
    forgetEnv('APP_KEY');

    expect(($this->run)('key:generate'))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('Usa --force')
        ->and(file_get_contents($this->project . '/.env'))->toBe($first)
        ->and(($this->run)('key:generate', '--force'))->toBe(0)
        ->and(file_get_contents($this->project . '/.env'))->not->toBe($first)
        ->and(($this->run)('key:generate', '--show'))->toBe(0)
        ->and(consoleOutput($this->output))->toMatch('/base64:[A-Za-z0-9+\/=]{44}/');
});

it('agrega APP_KEY a un .env que no la tiene', function (): void {
    writeFile($this->project . '/.env', "APP_NAME=Tienda\n");

    ($this->run)('key:generate');

    expect((string) file_get_contents($this->project . '/.env'))->toMatch('/^APP_NAME=Tienda\nAPP_KEY=base64:/');
});

it('diagnostica el proyecto con doctor', function (): void {
    expect(($this->run)('doctor'))->toBe(1);

    $output = consoleOutput($this->output);

    expect($output)->toContain('✓ PHP ' . PHP_VERSION)
        ->toContain('! No hay .env')
        ->toContain('! APP_KEY vacía')
        ->toContain('✓ Conexión a la base de datos (sqlite)')
        ->toContain('✓ Migraciones al día')
        ->toContain('✗ Falta public/assets/app.css')
        ->toContain('Hay 1 problema(s) que corregir.');
});

it('marca APP_DEBUG en producción y migraciones pendientes', function (): void {
    putenv('BZ_TOOLS_ENV=production');
    putenv('BZ_TOOLS_DEBUG=true');
    writeFile($this->project . '/public/assets/app.css', '');
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear_x.php', "<?php return new class () extends BootZen\\Database\\Migration { public function up(BootZen\\Database\\Connection \$db): void {} };");

    expect(($this->run)('doctor'))->toBe(1);

    expect(consoleOutput($this->output))->toContain('✗ APP_DEBUG=true en producción')
        ->toContain('! Hay 1 migración(es) pendiente(s)')
        ->toContain('config:cache');
});

it('termina sin errores cuando todo está listo', function (): void {
    writeFile($this->project . '/public/assets/app.css', '');
    writeFile($this->project . '/.env', "APP_KEY=base64:abc\n");

    expect(($this->run)('doctor'))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('Todo listo.');
});

it('reporta si no puede conectar a la base de datos', function (): void {
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'mysql', 'connections' => ['mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '1', 'database' => 'x']]];");

    ($this->run)('doctor');

    expect(consoleOutput($this->output))->toContain('✗ No se pudo conectar a la base de datos');
});

it('ejecuta formato, PHPStan y tests con check', function (): void {
    expect(($this->run)('check'))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('ejecuta composer install');

    mkdir($this->project . '/vendor/bin', 0775, true);

    expect(($this->run)('check'))->toBe(0)
        ->and($this->commands)->toHaveCount(3)
        ->and($this->commands[0])->toContain($this->project . '/vendor/bin/php-cs-fixer')
        ->and($this->commands[1])->toContain('analyse')
        ->and($this->commands[2])->toBe([PHP_BINARY, $this->project . '/vendor/bin/pest'])
        ->and(consoleOutput($this->output))->toContain('Todo en verde.');

    $this->commands = [];
    $this->status = 2;

    expect(($this->run)('check'))->toBe(2)
        ->and($this->commands)->toHaveCount(1)
        ->and(consoleOutput($this->output))->toContain('Falló: Formato');
});

it('genera archivos con make:* y pide el nombre si falta', function (): void {
    expect(($this->run)('make:resource', 'Task', 'title:string', 'done:bool', '--table=tareas'))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('app/Controllers/TaskController.php')->toContain('Siguiente: php bin/bootzen migrate')
        ->and((string) file_get_contents($this->project . '/app/Models/Task.php'))->toContain("'tareas'")
        ->and(($this->run)('make:model', 'Cliente', '--migration'))->toBe(0)
        ->and(($this->run)('make:controller', 'Factura'))->toBe(0)
        ->and(($this->run)('make:migration', 'agregar_telefono'))->toBe(0)
        ->and(($this->run)('make:middleware', 'SoloAdmins'))->toBe(0)
        ->and(($this->run)('make:seeder', 'ClientesSeeder'))->toBe(0)
        ->and(($this->run)('make:test', 'Factura', '--unit'))->toBe(0)
        ->and(($this->run)('make:component', 'modal', '--class'))->toBe(0)
        ->and($this->project . '/app/View/Components/Modal.php')->toBeFile()
        ->and($this->project . '/tests/Unit/FacturaTest.php')->toBeFile()
        ->and(($this->run)('make:model'))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('Falta el nombre. Uso: php bin/bootzen make:model Factura')
        ->and(($this->run)('make:resource', 'Task', 'title:string'))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('Ya existe');
});
