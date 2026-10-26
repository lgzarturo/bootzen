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
        ->and($project . '/vendor')->not->toBeDirectory()
        ->and(file_get_contents($project . '/composer.json'))->toContain('"app/demo-app"')
        ->and(file_get_contents($project . '/composer.json'))->not->toContain('__APP_');

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
