<?php

declare(strict_types=1);

use BootZen\Core\Response;

it('crea respuestas HTML', function (): void {
    $response = Response::html('<h1>Hola</h1>');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('content-type'))->toBe('text/html; charset=utf-8')
        ->and($response->getBody())->toBe('<h1>Hola</h1>');
});

it('crea respuestas JSON', function (): void {
    $response = Response::json(['ok' => true], 201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeader('content-type'))->toBe('application/json')
        ->and($response->getBody())->toBe('{"ok":true}');
});

it('crea redirecciones', function (): void {
    $response = Response::redirect('/login');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('location'))->toBe('/login');
});

it('es inmutable al agregar cabeceras', function (): void {
    $original = Response::html('x');
    $copia = $original->withHeader('X-Test', '1');

    expect($original->getHeader('x-test'))->toBeNull()
        ->and($copia->getHeader('x-test'))->toBe('1');
});
