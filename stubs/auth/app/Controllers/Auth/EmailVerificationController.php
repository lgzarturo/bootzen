<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Mail\VerificationEmail;
use App\Models\User;
use BootZen\Core\Response;
use BootZen\Mail\MailException;
use DateTimeImmutable;

/**
 * Verificación de correo: aviso "revisa tu correo", reenvío del enlace y el enlace firmado.
 * Las rutas que la exigen llevan el middleware 'verified'.
 */
final class EmailVerificationController
{
    public function notice(): Response
    {
        $user = $this->user();

        return $user->email_verified_at !== null
            ? redirect(home_url())
            : view('auth.verify-email', ['user' => $user]);
    }

    public function send(): Response
    {
        $user = $this->user();

        if ($user->email_verified_at !== null) {
            return redirect(home_url());
        }

        self::sendLink($user);

        return back()->with('status', __('Te enviamos un enlace nuevo. Revisa tu correo.'));
    }

    /**
     * Llega desde el correo con la firma (middleware signed). No pide sesión: el enlace ya demuestra
     * que la persona tiene acceso al correo.
     */
    public function verify(int $id, string $hash): Response
    {
        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->email), $hash)) {
            abort(403, 'El enlace no es válido o ya venció.');
        }

        if ($user->email_verified_at === null) {
            $user->email_verified_at = new DateTimeImmutable();
            $user->save();
        }

        return auth()->check()
            ? redirect(home_url())->with('status', __('Tu correo quedó verificado.'))
            : redirect(route('login'))->with('status', __('Tu correo quedó verificado. Ya puedes iniciar sesión.'));
    }

    /**
     * Envía el enlace. Si el correo falla queda en el log: el usuario puede pedir otro desde el aviso.
     */
    public static function sendLink(User $user): void
    {
        try {
            mailer()->send(VerificationEmail::for($user));
        } catch (MailException $e) {
            logger('No se pudo enviar el correo de verificación: ' . $e->getMessage(), 'error');
        }
    }

    private function user(): User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : abort(401);
    }
}
