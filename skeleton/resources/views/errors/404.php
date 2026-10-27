<?php

/**
 * Página 404. Reutiliza la página genérica con un mensaje propio,
 * salvo que abort(404, 'mensaje') haya indicado uno.
 */

$title = 'Página no encontrada';
$message = $message === 'Not Found' ? 'La página que buscas no existe o cambió de dirección.' : $message;

require __DIR__ . '/default.php';
