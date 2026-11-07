<?php

/**
 * Correo. Drivers: log (escribe el correo en storage/logs, para desarrollo), smtp (envío real)
 * y array (lo guarda en memoria, para tests).
 */

declare(strict_types=1);

return [
    'driver' => env('MAIL_DRIVER', 'log'),

    // Remitente por defecto; un Message puede usar otro con ->from()
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hola@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'BootZen')),
    ],

    'smtp' => [
        'host' => env('MAIL_HOST', '127.0.0.1'),
        'port' => env('MAIL_PORT', 587),
        // tls: STARTTLS (puerto 587). ssl: TLS desde el inicio (465). none: sin cifrar, solo servidores locales
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'timeout' => env('MAIL_TIMEOUT', 10),
        // Déjalo en true en producción: false acepta certificados que no se pueden verificar
        'verify_peer' => env('MAIL_VERIFY_PEER', true),
    ],
];
