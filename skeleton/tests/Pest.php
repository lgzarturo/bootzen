<?php

declare(strict_types=1);

use BootZen\Core\Application;

require_once __DIR__ . '/../framework/bootstrap.php';

/**
 * Crea una instancia nueva de la aplicación, igual que public/index.php.
 */
function createApp(): Application
{
    return require __DIR__ . '/../bootstrap/app.php';
}
