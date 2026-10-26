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

it('responde 404 en rutas desconocidas', function (): void {
    $response = createApp()->run(new Request('GET', '/no-existe'));

    expect($response->getStatusCode())->toBe(404);
});
