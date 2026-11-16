<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Security\RateLimiter;
use BootZen\Validation\ValidationException;

/**
 * Inicio y cierre de sesión. Después de 5 intentos fallidos por correo e IP bloquea un minuto, y después
 * de 30 por cuenta (desde cualquier IP) bloquea una hora.
 */
final class LoginController
{
    private const MAX_ATTEMPTS = 5;

    private const MAX_PER_ACCOUNT = 30;

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
        // Segundo tope solo por cuenta: frena a quien reparte los intentos entre muchas IP
        $cuenta = 'login-cuenta|' . $email;

        // El intento se cuenta antes de revisar la contraseña: las peticiones en paralelo no pasan de más
        foreach ([[$clave, self::MAX_ATTEMPTS, 60], [$cuenta, self::MAX_PER_ACCOUNT, 3600]] as [$key, $max, $seconds]) {
            if (! $this->limiter->attempt($key, $max, $seconds)) {
                throw ValidationException::withMessages([
                    'email' => __('Demasiados intentos. Intenta de nuevo en :seconds segundos.', ['seconds' => $this->limiter->availableIn($key)]),
                ]);
            }
        }

        if (! auth()->attempt(['email' => $email, 'password' => $datos['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        $this->limiter->clear($clave);
        $this->limiter->clear($cuenta);

        return redirect(auth()->intended(home_url()));
    }

    public function destroy(): Response
    {
        auth()->logout();

        return redirect(route('home'))->with('status', __('Cerraste sesión.'));
    }

    /**
     * Cierra la sesión en los demás dispositivos (pide la contraseña). Esta sigue abierta.
     */
    public function destroyOthers(Request $request): Response
    {
        $datos = $request->validate(['password' => 'required|string']);

        if (! auth()->logoutOtherDevices((string) $datos['password'])) {
            throw ValidationException::withMessages(['password' => __('La contraseña no es correcta.')]);
        }

        return back()->with('status', __('Cerraste la sesión en tus otros dispositivos.'));
    }
}
