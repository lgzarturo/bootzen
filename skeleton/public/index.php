<?php

/**
 * Front controller de la aplicación.
 *
 * Toda petición HTTP entra por aquí: se carga el framework, se construye la
 * aplicación en bootstrap/app.php y se envía la respuesta.
 */

declare(strict_types=1);

require __DIR__ . '/../framework/bootstrap.php';

/** @var \BootZen\Core\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$app->handle();
