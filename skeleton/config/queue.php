<?php

/**
 * Cola de trabajos. Drivers: sync (ejecuta el job en el momento, sin tabla ni worker) y database
 * (tablas jobs y failed_jobs, creadas con php bin/bootzen queue:table; las atiende queue:work).
 */

declare(strict_types=1);

return [
    'driver' => env('QUEUE_DRIVER', 'sync'),

    // Segundos tras los que un job reservado se da por abandonado (el worker murió) y se vuelve a tomar.
    // Debe ser mayor que lo que tarda tu job más largo
    'retry_after' => env('QUEUE_RETRY_AFTER', 300),

    'table' => 'jobs',
    'failed_table' => 'failed_jobs',
];
