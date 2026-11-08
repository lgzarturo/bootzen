<?php

/**
 * EnsureEmailIsVerified.php
 *
 * Middleware 'verified': solo deja pasar a usuarios con el correo verificado (columna
 * email_verified_at). Va después de 'auth'.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.6.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Auth\Auth;
use BootZen\Core\HttpException;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;

/**
 * Sin verificar: en páginas manda a la ruta 'verification.notice' (si existe); en JSON responde 403.
 *
 * @example $router->group(['middleware' => ['auth', 'verified']], function (Router $router): void { ... });
 */
final class EnsureEmailIsVerified implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth, private readonly Router $router)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user === null || $user->getAttribute('email_verified_at') !== null) {
            return $next($request);
        }

        if ($request->wantsJson() || $this->router->getRoute('verification.notice') === null) {
            throw new HttpException(403, 'Verifica tu correo para continuar.');
        }

        return Response::redirect(route('verification.notice'));
    }
}
