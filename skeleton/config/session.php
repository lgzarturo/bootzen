<?php

/**
 * Sesiones: se guardan como archivos en storage/sessions (driver file) o en memoria (array, para tests).
 *
 * Las usan los formularios (CSRF, errores de validación, old()), los mensajes flash y el login.
 */

declare(strict_types=1);

return [
    // file o array
    'driver' => env('SESSION_DRIVER', 'file'),

    // Minutos sin actividad antes de que la sesión expire
    'lifetime' => env('SESSION_LIFETIME', 120),

    'cookie' => env('SESSION_COOKIE', '__APP_SLUG__-session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN', ''),

    // null: cookie segura solo si la petición llega por HTTPS. true para forzarla
    'secure' => env('SESSION_SECURE_COOKIE'),

    // Lax protege contra CSRF y permite llegar desde enlaces externos
    'same_site' => 'Lax',

    // Rutas sin verificación CSRF (admite *), por ejemplo webhooks de terceros
    'csrf_except' => [
        // 'webhooks/*',
    ],
];
