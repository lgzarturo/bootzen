<?php

declare(strict_types=1);

use BootZen\Core\ExceptionHandler;
use BootZen\Core\HttpException;
use BootZen\Core\Logger;
use BootZen\Core\Request;

beforeEach(function (): void {
    $this->directory = makeTempDir();
});

afterEach(function (): void {
    removeDir($this->directory);
});

it('usa la página integrada si no hay vistas de error', function (): void {
    $response = (new ExceptionHandler(null, $this->directory))->render(new HttpException(404));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getBody())->toContain('404 - Not Found');
});

it('cae a la página integrada si la vista de error falla', function (): void {
    writeFile($this->directory . '/errors/500.php', '<?php throw new RuntimeException("vista rota");');

    $response = (new ExceptionHandler(null, $this->directory))->render(new RuntimeException('x'));

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->toContain('500 - Internal Server Error')
        ->and($response->getBody())->not->toContain('vista rota');
});

it('conserva las cabeceras de la HttpException', function (): void {
    $response = (new ExceptionHandler(null, $this->directory))
        ->render(new HttpException(429, 'Calma', ['Retry-After' => '60']));

    expect($response->getHeader('retry-after'))->toBe('60');
});

it('incluye la traza en JSON solo con debug', function (): void {
    $request = new Request('GET', '/', ['Accept' => 'application/json']);

    $conDebug = json_decode((new ExceptionHandler(null, $this->directory, true))->render(new LogicException('boom'), $request)->getBody(), true);
    $sinDebug = json_decode((new ExceptionHandler(null, $this->directory, false))->render(new LogicException('boom'), $request)->getBody(), true);

    expect($conDebug)->toHaveKeys(['exception', 'file', 'line', 'trace'])
        ->and($conDebug['message'])->toBe('boom')
        ->and($sinDebug)->not->toHaveKey('trace')
        ->and($sinDebug['message'])->not->toContain('boom');
});

it('no registra errores 4xx pero sí los 5xx', function (): void {
    $handler = new ExceptionHandler(new Logger($this->directory . '/logs'), $this->directory);

    $handler->report(new HttpException(404, 'no se registra'));
    $handler->report(new HttpException(503, 'mantenimiento'));

    $log = (string) file_get_contents($this->directory . '/logs/app-' . date('Y-m-d') . '.log');

    expect($log)->not->toContain('no se registra')
        ->and($log)->toContain('mantenimiento');
});
