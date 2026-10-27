<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Database\Migrator;

require_once __DIR__ . '/../framework/bootstrap.php';

/**
 * Crea una instancia nueva de la aplicación, igual que public/index.php.
 *
 * Usa una base SQLite en memoria (phpunit.xml) con todas las migraciones ya aplicadas:
 * cada llamada empieza con la base vacía.
 */
function createApp(): Application
{
    /** @var Application $app */
    $app = require __DIR__ . '/../bootstrap/app.php';

    $migrator = $app->make(Migrator::class);
    if ($migrator instanceof Migrator) {
        $migrator->run();
    }

    return $app;
}
