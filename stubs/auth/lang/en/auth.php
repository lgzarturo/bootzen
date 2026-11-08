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
    '¡Hola, :nombre! Te enviamos un enlace para verificar tu correo.' => 'Hi, :nombre! We sent you a link to verify your email.',
    'Hola, :nombre' => 'Hi, :nombre',
    'Esta página solo la ven los usuarios con sesión iniciada y el correo verificado (middleware auth y verified).' => 'Only logged in users with a verified email can see this page (auth and verified middleware).',
    'Cerraste sesión.' => 'You have logged out.',
    'El correo o la contraseña no son correctos.' => 'The email or password is incorrect.',
    'Demasiados intentos. Intenta de nuevo en :seconds segundos.' => 'Too many attempts. Try again in :seconds seconds.',
    'Si el correo está registrado, te enviamos un enlace para cambiar la contraseña.' => 'If the email is registered, we sent you a link to change your password.',
    'El enlace no es válido o ya venció. Pide uno nuevo.' => 'The link is invalid or has expired. Request a new one.',
    'Tu contraseña se actualizó. Ya puedes iniciar sesión.' => 'Your password was updated. You can log in now.',

    // Recordarme y sesiones
    'Recordarme en este dispositivo' => 'Remember me on this device',
    'Sesiones' => 'Sessions',
    'Si iniciaste sesión en otro equipo y ya no lo usas, ciérrala desde aquí.' => "If you logged in on another device you no longer use, log it out from here.",
    'Contraseña actual' => 'Current password',
    'Cerrar sesión en los demás dispositivos' => 'Log out other devices',
    'La contraseña no es correcta.' => 'The password is incorrect.',
    'Cerraste la sesión en tus otros dispositivos.' => 'You logged out of your other devices.',

    // Verificación de correo
    'Verifica tu correo' => 'Verify your email',
    'Te enviamos un enlace a :email. Ábrelo para activar tu cuenta.' => 'We sent a link to :email. Open it to activate your account.',
    'Si no lo ves, revisa la carpeta de spam o pide otro.' => "If you don't see it, check your spam folder or request another one.",
    'Reenviar enlace' => 'Resend link',
    'Te enviamos un enlace nuevo. Revisa tu correo.' => 'We sent you a new link. Check your email.',
    'Tu correo quedó verificado.' => 'Your email is verified.',
    'Tu correo quedó verificado. Ya puedes iniciar sesión.' => 'Your email is verified. You can log in now.',
    '¡Hola, :nombre!' => 'Hi, :nombre!',
    'Abre este enlace para verificar tu correo (vence en :horas horas):' => 'Open this link to verify your email (it expires in :horas hours):',
    'Si no creaste una cuenta, ignora este correo.' => "If you didn't create an account, ignore this email.",

    // Correo de recuperación
    'Recupera tu contraseña' => 'Reset your password',
    'Recibimos una solicitud para cambiar tu contraseña.' => 'We received a request to change your password.',
    'Abre este enlace para elegir una nueva (vence en :minutos minutos):' => 'Open this link to choose a new one (it expires in :minutos minutes):',
    'Si no fuiste tú, ignora este correo.' => "If it wasn't you, ignore this email.",
];
