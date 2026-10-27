<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Database\Migrator;
use BootZen\Testing\TestClient;

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

/**
 * Cliente HTTP para tests de feature, con una aplicación y una base de datos nuevas.
 *
 * Conserva la cookie de sesión entre peticiones. Créalo al inicio del test: los modelos que crees
 * después se guardan en su base de datos.
 *
 * @example client()->get('/')->assertOk()->assertSee('Hola');
 */
function client(): TestClient
{
    return new TestClient(createApp());
}
