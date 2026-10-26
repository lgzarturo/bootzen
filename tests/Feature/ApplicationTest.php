<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;

beforeEach(function (): void {
    $this->app = new Application();
});

it('resuelve una ruta registrada', function (): void {
    $this->app->get('/', static fn (): string => 'Hola');

    $response = $this->app->run(new Request('GET', '/'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody())->toBe('Hola');
});

it('convierte abort() en una respuesta con su código de estado', function (): void {
    $this->app->get('/privado', static fn () => abort(403, 'Sin permiso'));

    $response = $this->app->run(new Request('GET', '/privado'));

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getBody())->toContain('Sin permiso');
});

it('responde errores HTTP en JSON cuando la petición es JSON', function (): void {
    $this->app->get('/api/tarea', static fn () => abort(404, 'Tarea no encontrada'));

    $response = $this->app->run(new Request('GET', '/api/tarea', ['Content-Type' => 'application/json']));

    expect($response->getStatusCode())->toBe(404)
        ->and(json_decode($response->getBody(), true))->toMatchArray([
            'error' => 'Not Found',
            'message' => 'Tarea no encontrada',
        ]);
});

it('oculta el detalle de excepciones inesperadas fuera de debug', function (): void {
    $this->app->get('/falla', static function (): never {
        throw new RuntimeException('detalle interno');
    });

    // La excepción se registra con error_log(); se descarta para no ensuciar la salida de los tests
    $previousLog = ini_set('error_log', '/dev/null');
    $response = $this->app->run(new Request('GET', '/falla'));
    ini_set('error_log', (string) $previousLog);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->not->toContain('detalle interno');
});
