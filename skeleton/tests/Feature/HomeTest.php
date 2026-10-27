<?php

declare(strict_types=1);

it('muestra la página de inicio', function (): void {
    client()->get('/')->assertOk()->assertSee('Hola');
});

it('responde al health check', function (): void {
    client()->get('/up')->assertOk()->assertJson(['status' => 'ok']);
});

it('genera las URLs de las rutas con nombre', function (): void {
    createApp();

    expect(route('home'))->toBe('/')
        ->and(route('up'))->toBe('/up');
});

it('muestra la página 404 de la aplicación', function (): void {
    client()->get('/no-existe')->assertNotFound()->assertSee('Página no encontrada');
});

it('tiene la base de datos lista para los tests', function (): void {
    createApp();

    expect(db()->scalar('SELECT 1'))->toBe(1)
        ->and(db()->driver())->toBe('sqlite');
});

it('agrega las cabeceras de seguridad', function (): void {
    client()->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy');
});
