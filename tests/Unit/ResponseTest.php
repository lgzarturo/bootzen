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

it('crea respuestas sin contenido y agrega varias cabeceras', function (): void {
    $response = Response::noContent()->withHeaders(['Cache-Control' => 'no-store', 'X-A' => '1']);

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getBody())->toBe('')
        ->and($response->getHeader('cache-control'))->toBe('no-store')
        ->and($response->getHeader('x-a'))->toBe('1');
});

it('codifica listas y caracteres especiales en JSON sin escapar', function (): void {
    expect(Response::json([['url' => 'https://a.b/c', 'nombre' => 'Peña']])->getBody())
        ->toBe('[{"url":"https://a.b/c","nombre":"Peña"}]');
});
