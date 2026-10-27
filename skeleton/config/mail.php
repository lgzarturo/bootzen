<?php

/**
 * Correo. Por ahora hay dos drivers: log (escribe el correo en storage/logs) y array (tests).
 * El envío por SMTP llega en una versión posterior.
 */

declare(strict_types=1);

return [
    'driver' => env('MAIL_DRIVER', 'log'),
];
