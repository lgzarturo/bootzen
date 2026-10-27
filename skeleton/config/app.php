<?php

/**
 * Configuración general de la aplicación.
 *
 * Se lee con config('app.clave'). Los valores sensibles o que cambian por entorno
 * van en .env y se leen aquí con env().
 */

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'BootZen'),

    // local, staging o production
    'env' => env('APP_ENV', 'production'),

    // Muestra la página de depuración en errores 500. Nunca en producción
    'debug' => env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost:8000'),

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'locale' => 'es',

    // Clave para firmar y cifrar datos (sesiones, tokens). La genera bootzen-new
    'key' => env('APP_KEY'),

    // Nivel mínimo que se escribe en storage/logs
    'log_level' => env('LOG_LEVEL', 'debug'),

    // Instalación desde el navegador (/_bootzen/instalar) para hostings sin terminal. Solo para
    // la primera instalación: se bloquea sola al terminar y conviene volver a ponerla en false
    'installer' => env('INSTALL_ENABLED', false),
];
