<?php

// Definir el stub para el archivo phar
Phar::mapPhar('bootzen.phar');

// Registrar autoloader para el namespace BootZen\
spl_autoload_register(function ($class) {
    $prefix = 'BootZen\\';
    $baseDir = 'phar://bootzen.phar/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // no es de BootZen
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Bootstrap principal del framework
require 'phar://bootzen.phar/index.php';

__HALT_COMPILER();
