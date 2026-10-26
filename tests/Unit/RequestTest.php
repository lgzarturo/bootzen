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

it('combina query y cuerpo en input() con notación de puntos', function (): void {
    $request = new Request('POST', '/pedido?origen=web&cantidad=1', [], [
        'cantidad' => '3',
        'items' => [['precio' => '10.5']],
        '_method' => 'POST',
    ]);

    expect($request->input('cantidad'))->toBe('3')
        ->and($request->input('origen'))->toBe('web')
        ->and($request->input('items.0.precio'))->toBe('10.5')
        ->and($request->input('items.1.precio', 'no'))->toBe('no')
        ->and($request->all())->not->toHaveKey('_method')
        ->and($request->only(['origen', 'falta']))->toBe(['origen' => 'web'])
        ->and(array_keys($request->except(['items'])))->toBe(['origen', 'cantidad']);
});

it('distingue has, filled, boolean e integer', function (): void {
    $request = new Request('POST', '/', [], ['vacio' => '  ', 'activo' => 'on', 'edad' => '30', 'nombre' => 'Ana']);

    expect($request->has('vacio'))->toBeTrue()
        ->and($request->filled('vacio'))->toBeFalse()
        ->and($request->filled('nombre'))->toBeTrue()
        ->and($request->boolean('activo'))->toBeTrue()
        ->and($request->boolean('falta', true))->toBeTrue()
        ->and($request->integer('edad'))->toBe(30)
        ->and($request->integer('nombre', -1))->toBe(-1);
});

it('simula métodos con _method o la cabecera de override solo desde POST', function (): void {
    expect((new Request('POST', '/', [], ['_method' => 'put']))->getMethod())->toBe('PUT')
        ->and((new Request('POST', '/', ['X-HTTP-Method-Override' => 'DELETE']))->getMethod())->toBe('DELETE')
        ->and((new Request('POST', '/', [], ['_method' => 'GET']))->getMethod())->toBe('POST')
        ->and((new Request('GET', '/', [], ['_method' => 'DELETE']))->getMethod())->toBe('GET');
});

it('detecta si el cliente espera JSON', function (): void {
    expect((new Request('GET', '/', ['Accept' => 'application/json']))->wantsJson())->toBeTrue()
        ->and((new Request('GET', '/', ['Accept' => 'application/vnd.api+json']))->wantsJson())->toBeTrue()
        ->and((new Request('GET', '/', ['Accept' => 'text/html']))->wantsJson())->toBeFalse();
});

it('guarda atributos de forma inmutable', function (): void {
    $original = new Request('GET', '/tareas/5');
    $conParametros = $original->withAttributes(['id' => '5']);

    expect($original->param('id'))->toBeNull()
        ->and($conParametros->param('id'))->toBe('5')
        ->and($conParametros->params())->toBe(['id' => '5']);
});

it('analiza URIs que empiezan con doble barra como paths', function (): void {
    expect((new Request('GET', '//tareas?x=1'))->getPath())->toBe('/tareas');
});
