<?php

/**
 * CORS del grupo api (alias 'cors'): qué otros dominios pueden llamar a tus rutas JSON desde el navegador.
 */

declare(strict_types=1);

return [
    // Orígenes permitidos: ['https://app.midominio.com']. '*' permite cualquiera (solo para APIs públicas)
    'allowed_origins' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token'],

    // Cabeceras de la respuesta que el JavaScript del otro dominio puede leer
    'exposed_headers' => [],

    // Segundos que el navegador recuerda el permiso (preflight)
    'max_age' => 86400,

    // true envía cookies entre dominios; exige listar los orígenes (no vale '*')
    'credentials' => false,
];
