<?php

declare(strict_types=1);

use BootZen\Core\Container;
use BootZen\Core\Request;
use BootZen\Core\Router;

final class RouterTestController
{
    public function show(Request $request, string $id): string
    {
        return "tarea {$id}";
    }
}

it('despacha closures y convierte arrays a JSON', function (): void {
    $router = new Router(new Container());
    $router->get('/api', static fn (): array => ['ok' => true]);

    $response = $router->resolve(new Request('GET', '/api'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody())->toBe('{"ok":true}');
});

it('pasa los parámetros de ruta al controlador', function (): void {
    $router = new Router(new Container());
    $router->get('/tareas/{id}', [RouterTestController::class, 'show']);

    expect($router->resolve(new Request('GET', '/tareas/42'))->getBody())->toBe('tarea 42');
});

it('aplica el prefijo de los grupos', function (): void {
    $router = new Router(new Container());
    $router->group(['prefix' => '/admin'], function (Router $router): void {
        $router->get('/panel', static fn (): string => 'panel');
    });

    expect($router->resolve(new Request('GET', '/admin/panel'))->getBody())->toBe('panel');
});

it('responde 404 si la ruta no existe', function (): void {
    $router = new Router(new Container());

    expect($router->resolve(new Request('GET', '/nada'))->getStatusCode())->toBe(404);
});
