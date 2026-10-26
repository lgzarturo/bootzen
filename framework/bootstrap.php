<?php

/**
 * bootstrap.php
 *
 * Punto de carga del framework BootZen. Registra el autoloader PSR-4 para los
 * namespaces BootZen\ (framework/src) y App\ (app/) y carga los helpers globales.
 *
 * No depende de Composer: un proyecto funciona en producción sin vendor/.
 * Si vendor/autoload.php existe (entorno de desarrollo), también se carga.
 *
 * @package    BootZen
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

if (! defined('BOOTZEN_BASE_PATH')) {
    define('BOOTZEN_BASE_PATH', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'BootZen\\' => __DIR__ . '/src/',
        'App\\' => BOOTZEN_BASE_PATH . '/app/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }

        return;
    }
});

if (is_file(BOOTZEN_BASE_PATH . '/vendor/autoload.php')) {
    require_once BOOTZEN_BASE_PATH . '/vendor/autoload.php';
}

require_once __DIR__ . '/src/Helpers/helpers.php';
