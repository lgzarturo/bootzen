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

    // Proxies de confianza: de quién se aceptan X-Forwarded-For (IP real del visitante) y X-Forwarded-Proto
    // (HTTPS). Vacío: la app no está detrás de un proxy y solo cuenta la IP de la conexión. Si hay un
    // proxy o balanceador delante, pon su IP o rango ('10.0.0.5', '172.16.0.0/12'), o 'private' si llega
    // por una red privada o desde el mismo servidor (Docker, Nginx o Caddy locales). Nunca '*' salvo que el
    // servidor solo sea alcanzable a través del proxy.
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
];
