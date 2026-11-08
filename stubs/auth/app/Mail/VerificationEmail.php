<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use BootZen\Mail\Message;

/**
 * Correo con el enlace para verificar la dirección. El enlace va firmado con la APP_KEY y vence en
 * config('auth.verify_expire') minutos; si el usuario cambia su correo, el enlace viejo deja de servir.
 *
 * @example mailer()->send(VerificationEmail::for($user));
 */
final class VerificationEmail
{
    public static function for(User $user): Message
    {
        $minutes = config('auth.verify_expire', 60 * 24);
        $minutes = is_int($minutes) ? $minutes : 60 * 24;
        $link = signed_route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)], $minutes);

        return new Message(
            $user->email,
            __('Verifica tu correo'),
            __('¡Hola, :nombre!', ['nombre' => $user->nombre]) . "\n\n"
            . __('Abre este enlace para verificar tu correo (vence en :horas horas):', ['horas' => intdiv($minutes, 60)]) . "\n{$link}\n\n"
            . __('Si no creaste una cuenta, ignora este correo.')
        );
    }
}
