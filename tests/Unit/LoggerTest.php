<?php

declare(strict_types=1);

use BootZen\Core\Logger;

beforeEach(function (): void {
    $this->directory = makeTempDir();
});

afterEach(function (): void {
    removeDir($this->directory);
});

it('escribe en el archivo diario con nivel, mensaje interpolado y contexto', function (): void {
    (new Logger($this->directory))->error('Pago {id} rechazado', ['id' => 42, 'motivo' => 'fondos']);

    $contents = (string) file_get_contents($this->directory . '/app-' . date('Y-m-d') . '.log');

    expect($contents)->toContain('app.ERROR: Pago 42 rechazado')
        ->and($contents)->toContain('{"id":42,"motivo":"fondos"}');
});

it('descarta los mensajes por debajo del nivel mínimo', function (): void {
    $logger = new Logger($this->directory, 'warning');
    $logger->info('no se escribe');
    $logger->warning('sí se escribe');

    $contents = (string) file_get_contents($this->directory . '/app-' . date('Y-m-d') . '.log');

    expect($contents)->not->toContain('no se escribe')
        ->and($contents)->toContain('sí se escribe');
});

it('resume las excepciones del contexto', function (): void {
    (new Logger($this->directory))->critical('Falla', ['exception' => new RuntimeException('boom')]);

    expect((string) file_get_contents($this->directory . '/app-' . date('Y-m-d') . '.log'))
        ->toContain('RuntimeException: boom en');
});

it('rechaza niveles desconocidos', function (): void {
    (new Logger($this->directory))->log('fatal', 'x');
})->throws(InvalidArgumentException::class, 'Nivel de log desconocido');

it('no deja que un mensaje con saltos de línea finja otra entrada', function (): void {
    (new Logger($this->directory))->warning("Login fallido de ana\n[2026-01-01 00:00:00] app.INFO: Login correcto de admin\r\notra");

    $file = $this->directory . '/app-' . date('Y-m-d') . '.log';
    $lines = explode("\n", trim((string) file_get_contents($file)));

    expect($lines)->toHaveCount(3)
        ->and($lines[1])->toBe('    [2026-01-01 00:00:00] app.INFO: Login correcto de admin')
        ->and($lines[2])->toBe('    otra')
        ->and(fileperms($file) & 0777)->toBe(0600);
});
