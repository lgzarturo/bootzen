<?php

/**
 * Rutas de las páginas: todas pasan por el grupo web (sesión, CSRF y errores de validación).
 * Las rutas JSON van en routes/api.php.
 *
 * Usa siempre [Controlador::class, 'metodo'] y ponle nombre a cada ruta con ->name()
 * para generar URLs con route('nombre').
 *
 * Lo que va dentro de $router->localized() existe en cada idioma de APP_LOCALES: el de APP_LOCALE
 * sin prefijo (/panel) y los demás con prefijo (/en/panel). route() elige la del idioma activo.
 */

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use BootZen\Core\Router;

return static function (Router $router): void {
    // Health check para balanceadores, Docker y monitoreo
    $router->get('/up', static fn (): array => ['status' => 'ok'])->name('up');

    // Páginas en cada idioma (/panel y /en/panel)
    $router->localized(static function (Router $router): void {
        $router->get('/', [HomeController::class, 'index'])->name('home');

        // Registro, login, logout y recuperación de contraseña
        (require __DIR__ . '/auth.php')($router);

        // Páginas solo para usuarios autenticados
        $router->group(['middleware' => 'auth'], static function (Router $router): void {
            $router->get('/panel', [DashboardController::class, 'index'])->name('dashboard');
        });
    });
};
