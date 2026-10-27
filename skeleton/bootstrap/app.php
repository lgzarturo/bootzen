<?php

/**
 * Construye la aplicación: carga .env y config/, registra middleware y rutas.
 *
 * Lo usan public/index.php y los tests, así ambos arrancan exactamente igual.
 */

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Router;
use BootZen\Middleware\Authenticate;
use BootZen\Middleware\CorsMiddleware;
use BootZen\Middleware\RateLimit;
use BootZen\Middleware\RedirectIfAuthenticated;
use BootZen\Middleware\SecurityHeaders;
use BootZen\Middleware\StartSession;
use BootZen\Middleware\VerifyCsrfToken;

$app = new Application(dirname(__DIR__));

// Middleware global (todas las peticiones)
$app->addGlobalMiddleware(SecurityHeaders::class);

// Alias para usar en rutas: ->middleware('auth'), ->middleware('throttle:60,1')
$app->aliasMiddleware('auth', Authenticate::class);
$app->aliasMiddleware('guest', RedirectIfAuthenticated::class);
$app->aliasMiddleware('throttle', RateLimit::class);
$app->aliasMiddleware('cors', CorsMiddleware::class);

// Grupos: web (sesión y CSRF) para páginas y formularios; api (CORS y límite) para JSON sin sesión
$app->middlewareGroup('web', [StartSession::class, VerifyCsrfToken::class]);
$app->middlewareGroup('api', ['cors', 'throttle:60,1']);

$app->routes(static function (Router $router): void {
    $router->group(['middleware' => 'web'], require __DIR__ . '/../routes/web.php');
    $router->group(['prefix' => '/api', 'as' => 'api.', 'middleware' => 'api'], require __DIR__ . '/../routes/api.php');
});

return $app;
