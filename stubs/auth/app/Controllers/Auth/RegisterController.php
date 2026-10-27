<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Models\User;
use BootZen\Auth\Hash;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Registro de usuarios.
 */
final class RegisterController
{
    public function create(): Response
    {
        return view('auth.register');
    }

    public function store(Request $request): Response
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:80',
            'email' => 'required|email|max:190|unique:users',
            'password' => 'required|string|min:8|max:200|confirmed',
        ]);

        $user = User::create([
            'nombre' => $datos['nombre'],
            'email' => strtolower((string) $datos['email']),
            'password' => Hash::make((string) $datos['password']),
        ]);

        auth()->login($user);

        return redirect(route('dashboard'))->with('status', "¡Hola, {$user->nombre}! Tu cuenta está lista.");
    }
}
