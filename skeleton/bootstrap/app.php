<?php

/**
 * Construye la aplicación: carga .env y config/, registra middleware y rutas.
 *
 * Lo usan public/index.php y los tests, así ambos arrancan exactamente igual.
 */

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Middleware\CorsMiddleware;

$app = new Application(dirname(__DIR__));

// Alias de middleware para usar en rutas: ->middleware('cors')
$app->aliasMiddleware('cors', CorsMiddleware::class);

// Grupos de middleware: ->middleware('api') o en $router->group(['middleware' => 'api'], ...)
$app->middlewareGroup('api', ['cors']);

// Middleware global (todas las peticiones): $app->addGlobalMiddleware(MiMiddleware::class);

$app->routes(require __DIR__ . '/../routes/web.php');

return $app;
