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
        // Cada registro envía un correo: sin límite serviría para crear cuentas en masa y escribirle a terceros
        $router->post('/registro', [RegisterController::class, 'store'])
            ->name('register.store')
            ->middleware('throttle:5,10,registro');
        $router->get('/login', [LoginController::class, 'create'])->name('login');
        // Tope por IP; el controlador agrega el suyo por correo e IP y otro por cuenta
        $router->post('/login', [LoginController::class, 'store'])
            ->name('login.store')
            ->middleware('throttle:20,1,login');

        $router->get('/recuperar-contrasena', [PasswordResetController::class, 'request'])->name('password.request');
        $router->post('/recuperar-contrasena', [PasswordResetController::class, 'email'])
            ->name('password.email')
            ->middleware('throttle:5,1,recuperar');
        $router->get('/restablecer-contrasena/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        $router->post('/restablecer-contrasena', [PasswordResetController::class, 'update'])
            ->name('password.update')
            ->middleware('throttle:10,1,restablecer');
    });

    $router->post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

    // Verificación de correo: aviso y reenvío con sesión; el enlace del correo va firmado y no pide sesión
    $router->group(['middleware' => 'auth'], static function (Router $router): void {
        $router->get('/verificar-correo', [EmailVerificationController::class, 'notice'])->name('verification.notice');
        $router->post('/verificar-correo', [EmailVerificationController::class, 'send'])
            ->name('verification.send')
            ->middleware('throttle:3,1,verificar-envio');
        $router->post('/sesiones/cerrar-otras', [LoginController::class, 'destroyOthers'])
            ->name('sessions.destroy-others')
            ->middleware('throttle:5,1,cerrar-otras');
    });
    $router->get('/verificar-correo/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify')
        ->middleware(['signed', 'throttle:6,1,verificar']);
};
