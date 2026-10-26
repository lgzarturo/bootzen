<?php

declare(strict_types=1);

use BootZen\Core\Container;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;
use BootZen\Middleware\CorsMiddleware;

it('agrega Access-Control-Allow-Origin a las respuestas', function (): void {
    $response = (new CorsMiddleware())->process(
        new Request('GET', '/', ['Origin' => 'https://app.ejemplo.com']),
        static fn (): Response => Response::json([])
    );

    expect($response->getHeader('access-control-allow-origin'))->toBe('https://app.ejemplo.com');
});

it('solo permite orígenes listados cuando hay credenciales', function (): void {
    $cors = new CorsMiddleware(['allowed_origins' => ['https://ok.com'], 'credentials' => true]);
    $next = static fn (): Response => Response::json([]);

    $permitido = $cors->process(new Request('GET', '/', ['Origin' => 'https://ok.com']), $next);
    $rechazado = $cors->process(new Request('GET', '/', ['Origin' => 'https://otro.com']), $next);

    expect($permitido->getHeader('access-control-allow-origin'))->toBe('https://ok.com')
        ->and($permitido->getHeader('access-control-allow-credentials'))->toBe('true')
        ->and($rechazado->getHeader('access-control-allow-origin'))->toBeNull();
});

it('responde el preflight de una ruta con cors como middleware de ruta', function (): void {
    $router = new Router(new Container());
    $router->aliasMiddleware('cors', CorsMiddleware::class);
    $router->post('/api/tareas', static fn (): array => [])->middleware('cors');

    $response = $router->resolve(new Request('OPTIONS', '/api/tareas', ['Origin' => 'https://app.ejemplo.com']));

    expect($response->getHeader('access-control-allow-origin'))->toBe('https://app.ejemplo.com')
        ->and($response->getHeader('access-control-allow-methods'))->toContain('POST');
});
