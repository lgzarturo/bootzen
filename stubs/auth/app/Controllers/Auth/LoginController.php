<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Security\RateLimiter;
use BootZen\Validation\ValidationException;

/**
 * Inicio y cierre de sesión. Después de 5 intentos fallidos por correo e IP bloquea un minuto.
 */
final class LoginController
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function create(): Response
    {
        return view('auth.login');
    }

    public function store(Request $request): Response
    {
        $datos = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower((string) $datos['email']);
        $clave = 'login|' . $email . '|' . $request->getClientIp();

        if ($this->limiter->tooManyAttempts($clave, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Intenta de nuevo en ' . $this->limiter->availableIn($clave) . ' segundos.',
            ]);
        }

        if (! auth()->attempt(['email' => $email, 'password' => $datos['password']])) {
            $this->limiter->hit($clave, 60);

            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        $this->limiter->clear($clave);

        return redirect(auth()->intended(route('dashboard')));
    }

    public function destroy(): Response
    {
        auth()->logout();

        return redirect(route('home'))->with('status', 'Cerraste sesión.');
    }
}
