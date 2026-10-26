<?php

declare(strict_types=1);

use BootZen\Core\Request;

it('separa path y query string', function (): void {
    $request = new Request('get', '/tareas?page=2&orden=fecha');

    expect($request->getMethod())->toBe('GET')
        ->and($request->getPath())->toBe('/tareas')
        ->and($request->getQuery('page'))->toBe('2')
        ->and($request->getQuery('falta', 'x'))->toBe('x');
});

it('normaliza las cabeceras sin importar mayúsculas', function (): void {
    $request = new Request('POST', '/', ['Content-Type' => 'application/json']);

    expect($request->getHeader('content-type'))->toBe('application/json')
        ->and($request->isJson())->toBeTrue();
});

it('expone el cuerpo de la petición', function (): void {
    $request = new Request('POST', '/tareas', [], ['titulo' => 'Comprar pan']);

    expect($request->getBody('titulo'))->toBe('Comprar pan')
        ->and($request->getBody('falta', 'def'))->toBe('def');
});
