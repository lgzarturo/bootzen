
<?php

// Leer la versión desde el archivo VERSION
$version = trim(file_get_contents(__DIR__ . '/VERSION'));
$pharFile = "bootzen-$version.phar";

// Si ya existe lo borramos
if (file_exists($pharFile)) {
    unlink($pharFile);
}

// Creamos el phar
$phar = new Phar($pharFile, 0, basename($pharFile));
$phar->buildFromDirectory(__DIR__ . '/core/src');

// Definir stub
$stub = file_get_contents(__DIR__ . '/core/stub.php');
$phar->setStub($stub);


// No firmar el phar para evitar problemas de integridad al copiar/renombrar

// Hacemos el phar ejecutable
chmod($pharFile, 0755);
echo "Phar generado: $pharFile\n";
