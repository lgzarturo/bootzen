<?php

/**
 * Cabeceras de seguridad que agrega el middleware SecurityHeaders a todas las respuestas.
 */

declare(strict_types=1);

return [
    // Content-Security-Policy. Si cargas fuentes, scripts o imágenes de otro dominio, agrégalo aquí.
    // null la desactiva (no recomendado)
    'csp' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
        . "font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; "
        . "frame-ancestors 'self'",

    // Strict-Transport-Security en peticiones HTTPS
    'hsts' => true,
];
