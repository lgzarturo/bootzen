<?php

/**
 * Autenticación con sesión: auth()->attempt(), auth()->user() y los middleware auth y guest.
 */

declare(strict_types=1);

return [
    // Modelo de usuario (necesita las columnas email y password)
    'model' => 'App\Models\User',

    // A dónde va un usuario autenticado que entra a una página de invitados (login, registro): nombre de
    // ruta (respeta el idioma de $router->localized()) o ruta que empieza con /
    'home' => 'dashboard',

    // Minutos que dura un enlace de recuperación de contraseña
    'reset_expire' => 60,

    // Minutos que dura el enlace para verificar el correo
    'verify_expire' => 60 * 24,

    // "Recordarme": días que dura la sesión en el dispositivo y nombre de la cookie
    'remember_days' => 30,
    'remember_cookie' => 'bootzen_remember',
];
