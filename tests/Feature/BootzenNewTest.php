<?php

declare(strict_types=1);

/**
 * Ejecuta un comando y devuelve [código de salida, salida combinada].
 *
 * @return array{0: int, 1: string}
 */
function runCommand(string $command, string $cwd): array
{
    exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1', $output, $status);

    return [$status, implode("\n", $output)];
}

beforeEach(function (): void {
    $this->workspace = sys_get_temp_dir() . '/bootzen-new-' . bin2hex(random_bytes(4));
    mkdir($this->workspace);
});

afterEach(function (): void {
    exec('rm -rf ' . escapeshellarg($this->workspace));
});

it('crea un proyecto que responde sin Composer', function (): void {
    $bin = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/bootzen-new');
    [$status] = runCommand($bin . ' demo-app --no-git', $this->workspace);

    $project = $this->workspace . '/demo-app';

    expect($status)->toBe(0)
        ->and($project . '/framework/bootstrap.php')->toBeFile()
        ->and($project . '/AGENTS.md')->toBeFile()
        ->and(is_executable($project . '/bin/bootzen'))->toBeTrue()
        ->and($project . '/public/assets/app.css')->toBeFile()
        ->and($project . '/public/assets/js/bootzen.js')->toBeFile()
        ->and($project . '/vendor')->not->toBeDirectory()
        ->and(file_get_contents($project . '/composer.json'))->toContain('"app/demo-app"')
        ->and(file_get_contents($project . '/composer.json'))->not->toContain('__APP_')
        ->and(file_get_contents($project . '/.env'))->toContain('APP_NAME="demo-app"')
        ->and(file_get_contents($project . '/.env'))->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m');

    $script = 'require "framework/bootstrap.php"; $app = require "bootstrap/app.php";'
        . ' echo $app->run(new BootZen\Core\Request("GET", "/"))->getBody();';
    [$status, $output] = runCommand(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script), $project);

    expect($status)->toBe(0)
        ->and($output)->toContain('Hola desde demo-app');
});

it('rechaza nombres inválidos y proyectos existentes', function (): void {
    $bin = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/bootzen-new');

    [$status, $output] = runCommand($bin . ' "../fuera" --no-git', $this->workspace);
    expect($status)->toBe(1)->and($output)->toContain('inválido');

    mkdir($this->workspace . '/existe');
    [$status, $output] = runCommand($bin . ' existe --no-git', $this->workspace);
    expect($status)->toBe(1)->and($output)->toContain('Ya existe');
});

it('agrega el scaffold de autenticación con --auth', function (): void {
    $bin = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/bootzen-new');
    [$status] = runCommand($bin . ' con-auth --auth --no-git', $this->workspace);
    $project = $this->workspace . '/con-auth';

    expect($status)->toBe(0)
        ->and($project . '/app/Models/User.php')->toBeFile()
        ->and($project . '/app/Controllers/Auth/LoginController.php')->toBeFile()
        ->and($project . '/routes/auth.php')->toBeFile()
        ->and($project . '/tests/Feature/AuthTest.php')->toBeFile()
        ->and(file_get_contents($project . '/routes/web.php'))->toContain("require __DIR__ . '/auth.php'")
        ->and(file_get_contents($project . '/config/auth.php'))->toContain("'home' => '/panel'");

    // Migra una base temporal y renderiza el login, que usa la sesión y el layout con el menú de usuario
    $script = 'putenv("DB_DATABASE=" . sys_get_temp_dir() . "/bootzen-auth-" . getmypid() . ".sqlite");'
        . ' require "framework/bootstrap.php"; $app = require "bootstrap/app.php";'
        . ' $app->make(BootZen\Database\Migrator::class)->run();'
        . ' echo $app->run(new BootZen\Core\Request("GET", "/login"))->getBody();'
        . ' unlink(getenv("DB_DATABASE"));';
    [$status, $output] = runCommand(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script), $project);

    expect($status)->toBe(0)
        ->and($output)->toContain('Iniciar sesión')
        ->and($output)->toContain('name="_token"')
        ->and($output)->toContain('Crear cuenta');
});

it('muestra la ayuda con las opciones', function (): void {
    [$status, $output] = runCommand(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/bootzen-new') . ' --help', $this->workspace);

    expect($status)->toBe(0)
        ->and($output)->toContain('--auth');
});

it('crea la app de demostración con --demo (incluye --auth)', function (): void {
    $bin = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/bootzen-new');
    [$status, $output] = runCommand($bin . ' con-demo --demo --no-git', $this->workspace);
    $project = $this->workspace . '/con-demo';

    expect($status)->toBe(0)
        ->and($output)->toContain('db:seed')
        ->and($project . '/app/Controllers/Auth/LoginController.php')->toBeFile()
        ->and($project . '/app/Controllers/TareaController.php')->toBeFile()
        ->and($project . '/resources/views/components/modal.php')->toBeFile()
        ->and($project . '/public/assets/js/components/modal.js')->toBeFile()
        ->and($project . '/tests/Feature/TareaTest.php')->toBeFile()
        ->and((string) file_get_contents($project . '/llms.txt'))->toStartWith('# con-demo')
        ->and(file_get_contents($project . '/config/auth.php'))->toContain("'home' => '/tareas'");

    // Migra, siembra y pide las tareas de Ana en JSON, sin servidor ni Composer
    $script = 'putenv("DB_DATABASE=" . sys_get_temp_dir() . "/bootzen-demo-" . getmypid() . ".sqlite");'
        . ' putenv("SESSION_DRIVER=array");'
        . ' require "framework/bootstrap.php"; $app = require "bootstrap/app.php";'
        . ' $app->make(BootZen\Database\Migrator::class)->run();'
        . ' $app->make(BootZen\Database\SeederRunner::class)->run();'
        . ' $client = new BootZen\Testing\TestClient($app);'
        . ' echo $client->actingAs(App\Models\User::where("email", "ana@ejemplo.com")->firstOrFail())->getJson("/tareas")->body();'
        . ' unlink(getenv("DB_DATABASE"));';
    [$status, $output] = runCommand(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script), $project);

    expect($status)->toBe(0, $output)
        ->and(json_decode($output, true)['total'] ?? null)->toBe(3);
});
