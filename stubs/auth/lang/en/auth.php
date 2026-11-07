<?php

/**
 * Inglés del scaffold --auth: registro, login, logout y recuperación de contraseña.
 */

declare(strict_types=1);

return [
    // Navegación
    'Entrar' => 'Log in',
    'Salir' => 'Log out',
    'Crear cuenta' => 'Sign up',
    'Panel' => 'Dashboard',

    // Formularios
    'Iniciar sesión' => 'Log in',
    'Correo' => 'Email',
    'Contraseña' => 'Password',
    'Nombre' => 'Name',
    'Repite la contraseña' => 'Repeat the password',
    '¿Olvidaste tu contraseña?' => 'Forgot your password?',
    '¿No tienes cuenta?' => "Don't have an account?",
    'Regístrate' => 'Sign up',
    '¿Ya tienes cuenta?' => 'Already have an account?',
    'Inicia sesión' => 'Log in',
    'Recuperar contraseña' => 'Reset password',
    'Te enviamos un enlace para elegir una nueva.' => "We'll send you a link to choose a new one.",
    'Enviar enlace' => 'Send link',
    'Volver a iniciar sesión' => 'Back to log in',
    'Nueva contraseña' => 'New password',
    'Elige una contraseña nueva' => 'Choose a new password',
    'Contraseña nueva' => 'New password',
    'Guardar contraseña' => 'Save password',

    // Nombres de campo en los mensajes de validación
    'nombre' => 'name',
    'password' => 'password',
    'password confirmation' => 'password confirmation',

    // Mensajes
    '¡Hola, :nombre! Tu cuenta está lista.' => 'Hi, :nombre! Your account is ready.',
    'Hola, :nombre' => 'Hi, :nombre',
    'Esta página solo la ven los usuarios con sesión iniciada (middleware auth).' => 'Only logged in users can see this page (auth middleware).',
    'Cerraste sesión.' => 'You have logged out.',
    'El correo o la contraseña no son correctos.' => 'The email or password is incorrect.',
    'Demasiados intentos. Intenta de nuevo en :seconds segundos.' => 'Too many attempts. Try again in :seconds seconds.',
    'Si el correo está registrado, te enviamos un enlace para cambiar la contraseña.' => 'If the email is registered, we sent you a link to change your password.',
    'El enlace no es válido o ya venció. Pide uno nuevo.' => 'The link is invalid or has expired. Request a new one.',
    'Tu contraseña se actualizó. Ya puedes iniciar sesión.' => 'Your password was updated. You can log in now.',

    // Correo de recuperación
    'Recupera tu contraseña' => 'Reset your password',
    'Recibimos una solicitud para cambiar tu contraseña.' => 'We received a request to change your password.',
    'Abre este enlace para elegir una nueva (vence en :minutos minutos):' => 'Open this link to choose a new one (it expires in :minutos minutes):',
    'Si no fuiste tú, ignora este correo.' => "If it wasn't you, ignore this email.",
];
