<?php

/**
 * Rutas de autenticación (scaffold --auth). Las incluye routes/web.php, así pasan por el grupo web.
 */

declare(strict_types=1);

use App\Controllers\Auth\EmailVerificationController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\PasswordResetController;
use App\Controllers\Auth\RegisterController;
use BootZen\Core\Router;

return static function (Router $router): void {
    $router->group(['middleware' => 'guest'], static function (Router $router): void {
        $router->get('/registro', [RegisterController::class, 'create'])->name('register');
        $router->post('/registro', [RegisterController::class, 'store'])->name('register.store');
        $router->get('/login', [LoginController::class, 'create'])->name('login');
        $router->post('/login', [LoginController::class, 'store'])->name('login.store');

        $router->get('/recuperar-contrasena', [PasswordResetController::class, 'request'])->name('password.request');
        $router->post('/recuperar-contrasena', [PasswordResetController::class, 'email'])
            ->name('password.email')
            ->middleware('throttle:5,1');
        $router->get('/restablecer-contrasena/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        $router->post('/restablecer-contrasena', [PasswordResetController::class, 'update'])->name('password.update');
    });

    $router->post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

    // Verificación de correo: aviso y reenvío con sesión; el enlace del correo va firmado y no pide sesión
    $router->group(['middleware' => 'auth'], static function (Router $router): void {
        $router->get('/verificar-correo', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        $router->post('/verificar-correo', [EmailVerificationController::class, 'send'])
            ->name('verification.send')
            ->middleware('throttle:3,1');
        $router->post('/sesiones/cerrar-otras', [LoginController::class, 'destroyOthers'])
            ->name('sessions.destroy-others')
            ->middleware('throttle:5,1');
    });
    $router->get('/verificar-correo/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify')
        ->middleware(['signed', 'throttle:6,1']);
};
