<?php

/**
 * Construye la aplicación: crea Application, registra middleware global y rutas.
 *
 * Lo usan public/index.php y los tests, así ambos arrancan exactamente igual.
 */

declare(strict_types=1);

use BootZen\Core\Application;

$app = new Application();

// Middleware global: $app->addGlobalMiddleware(MiMiddleware::class);

$app->routes(require __DIR__ . '/../routes/web.php');

return $app;
