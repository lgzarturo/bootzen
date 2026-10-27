<?php

declare(strict_types=1);

use BootZen\Core\Request;

it('muestra la página de inicio', function (): void {
    $response = createApp()->run(new Request('GET', '/'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody())->toContain('Hola');
});

it('responde al health check', function (): void {
    $response = createApp()->run(new Request('GET', '/up'));

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getBody(), true))->toBe(['status' => 'ok']);
});

it('genera las URLs de las rutas con nombre', function (): void {
    createApp();

    expect(route('home'))->toBe('/')
        ->and(route('up'))->toBe('/up');
});

it('muestra la página 404 de la aplicación', function (): void {
    $response = createApp()->run(new Request('GET', '/no-existe'));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getBody())->toContain('Página no encontrada');
});

it('tiene la base de datos lista para los tests', function (): void {
    createApp();

    expect(db()->scalar('SELECT 1'))->toBe(1)
        ->and(db()->driver())->toBe('sqlite');
});

it('agrega las cabeceras de seguridad', function (): void {
    $response = createApp()->run(new Request('GET', '/'));

    expect($response->getHeader('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->getHeader('Content-Security-Policy'))->toContain("default-src 'self'");
});
