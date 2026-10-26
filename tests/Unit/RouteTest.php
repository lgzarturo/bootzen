<?php

declare(strict_types=1);

use BootZen\Core\Route;

it('extrae parámetros y escapa la parte estática del patrón', function (): void {
    $route = new Route(['GET'], '/archivos/{nombre}.{ext}', null);

    expect($route->matches('/archivos/reporte.pdf'))->toBe(['nombre' => 'reporte', 'ext' => 'pdf'])
        ->and($route->matches('/archivos/reporte'))->toBeNull();
});

it('aplica restricciones en línea, incluso con cuantificadores', function (): void {
    $route = new Route(['GET'], '/posts/{anio:\d{4}}/{id:\d+}', null);

    expect($route->matches('/posts/2026/7'))->toBe(['anio' => '2026', 'id' => '7'])
        ->and($route->matches('/posts/26/7'))->toBeNull()
        ->and($route->matches('/posts/2026/abc'))->toBeNull();
});

it('aplica restricciones con where()', function (): void {
    $route = (new Route(['GET'], '/u/{slug}', null))->where('slug', '[a-z-]+');

    expect($route->matches('/u/ana-lopez'))->toBe(['slug' => 'ana-lopez'])
        ->and($route->matches('/u/Ana'))->toBeNull();
});

it('decodifica los parámetros', function (): void {
    expect((new Route(['GET'], '/buscar/{q}', null))->matches('/buscar/caf%C3%A9%20con%20leche'))
        ->toBe(['q' => 'café con leche']);
});

it('genera URLs con parámetros, codificación y query string', function (): void {
    $route = new Route(['GET'], '/tareas/{id:\d+}', null);

    expect($route->url(['id' => 5]))->toBe('/tareas/5')
        ->and($route->url(['id' => 5, 'tab' => 'notas']))->toBe('/tareas/5?tab=notas')
        ->and((new Route(['GET'], '/t/{tag}', null))->url(['tag' => 'a b']))->toBe('/t/a%20b');
});

it('valida los parámetros al generar URLs', function (array $parameters, string $message): void {
    (new Route(['GET'], '/tareas/{id:\d+}', null))->name('tareas.show')->url($parameters);
})->throws(InvalidArgumentException::class)->with([
    'faltante' => [[], 'Falta el parámetro [id]'],
    'inválido' => [['id' => 'abc'], 'no cumple la restricción'],
]);

it('acepta HEAD en rutas GET', function (): void {
    $route = new Route(['get'], '/', null);

    expect($route->allowsMethod('HEAD'))->toBeTrue()
        ->and($route->allowsMethod('POST'))->toBeFalse()
        ->and($route->getMethods())->toBe(['GET']);
});

it('lanza un error claro si la restricción no es una regex válida', function (): void {
    (new Route(['GET'], '/x/{id:[}', null))->matches('/x/1');
})->throws(InvalidArgumentException::class, 'Patrón inválido');
