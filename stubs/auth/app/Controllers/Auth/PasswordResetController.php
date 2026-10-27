<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Models\User;
use BootZen\Auth\Hash;
use BootZen\Auth\PasswordReset;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Mail\Message;
use BootZen\Validation\ValidationException;

/**
 * Recuperación de contraseña: pide el correo, envía un enlace con un token y permite elegir una nueva.
 * Con MAIL_DRIVER=log el correo (y el enlace) queda en storage/logs.
 */
final class PasswordResetController
{
    public function __construct(private readonly PasswordReset $resets)
    {
    }

    public function request(): Response
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): Response
    {
        $datos = $request->validate(['email' => 'required|email']);
        $email = strtolower((string) $datos['email']);

        if (User::where('email', $email)->exists()) {
            $token = $this->resets->create($email);
            $enlace = url(route('password.reset', ['token' => $token])) . '?email=' . rawurlencode($email);

            mailer()->send(new Message(
                $email,
                'Recupera tu contraseña',
                "Recibimos una solicitud para cambiar tu contraseña.\n\n"
                . "Abre este enlace para elegir una nueva (vence en " . config('auth.reset_expire', 60) . " minutos):\n{$enlace}\n\n"
                . 'Si no fuiste tú, ignora este correo.'
            ));
        }

        // La misma respuesta exista o no la cuenta, para no revelar qué correos están registrados
        return back()->with('status', 'Si el correo está registrado, te enviamos un enlace para cambiar la contraseña.');
    }

    public function edit(Request $request, string $token): Response
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->input('email', '')]);
    }

    public function update(Request $request): Response
    {
        $datos = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|max:200|confirmed',
        ]);

        $email = strtolower((string) $datos['email']);
        $user = User::where('email', $email)->first();

        if ($user === null || ! $this->resets->isValid($email, (string) $datos['token'])) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ya venció. Pide uno nuevo.']);
        }

        $user->update(['password' => Hash::make((string) $datos['password'])]);
        $this->resets->delete($email);

        return redirect(route('login'))->with('status', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
    }
}
