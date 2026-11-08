<?php

/**
 * Segmentos de URL en inglés para las rutas de $router->localized(): /registro queda /en/sign-up.
 * Solo traduce segmentos fijos; los parámetros ({id}) no cambian. La URL sin traducir (/en/registro)
 * redirige con 301 a la traducida.
 */

declare(strict_types=1);

return [
    // Autenticación
    'registro' => 'sign-up',
    'recuperar-contrasena' => 'forgot-password',
    'restablecer-contrasena' => 'reset-password',
    'verificar-correo' => 'verify-email',
    'panel' => 'dashboard',
    'sesiones' => 'sessions',
    'cerrar-otras' => 'log-out-others',
];
