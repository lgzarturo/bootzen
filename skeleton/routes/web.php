<?php

/**
 * Rutas de la aplicación.
 *
 * Usa siempre el formato [Controlador::class, 'metodo'] para rutas con lógica.
 */

declare(strict_types=1);

use App\Controllers\HomeController;
use BootZen\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);

    // Health check para balanceadores, Docker y monitoreo
    $router->get('/up', static fn (): array => ['status' => 'ok']);
};
