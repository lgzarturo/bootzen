<?php

/**
 * Autenticación con sesión: auth()->attempt(), auth()->user() y los middleware auth y guest.
 */

declare(strict_types=1);

return [
    // Modelo de usuario (necesita las columnas email y password)
    'model' => 'App\Models\User',

    // A dónde va un usuario autenticado que entra a una página de invitados (login, registro)
    'home' => '/',

    // Minutos que dura un enlace de recuperación de contraseña
    'reset_expire' => 60,
];
