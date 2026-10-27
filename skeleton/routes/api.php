<?php

/**
 * Rutas de la API JSON: todas llevan el prefijo /api, el nombre api. y el grupo api
 * (CORS y 60 peticiones por minuto por IP). No tienen sesión ni CSRF.
 */

declare(strict_types=1);

use BootZen\Core\Router;

return static function (Router $router): void {
    // $router->get('/tareas', [TareaApiController::class, 'index'])->name('tareas.index'); // GET /api/tareas
};
