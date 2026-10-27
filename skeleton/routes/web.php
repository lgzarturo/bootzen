<?php

/**
 * Rutas de las páginas: todas pasan por el grupo web (sesión, CSRF y errores de validación).
 * Las rutas JSON van en routes/api.php.
 *
 * Usa siempre [Controlador::class, 'metodo'] y ponle nombre a cada ruta con ->name()
 * para generar URLs con route('nombre').
 */

declare(strict_types=1);

use App\Controllers\HomeController;
use BootZen\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index'])->name('home');

    // Health check para balanceadores, Docker y monitoreo
    $router->get('/up', static fn (): array => ['status' => 'ok'])->name('up');
};
