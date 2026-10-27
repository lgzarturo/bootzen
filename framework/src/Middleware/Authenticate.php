<?php

/**
 * Authenticate.php
 *
 * Middleware 'auth': solo deja pasar a usuarios autenticados. A los invitados los manda a la ruta
 * 'login' (guardando la URL para volver después) o responde 401 si piden JSON.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
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
 * Va en rutas del grupo web: ->middleware('auth').
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth, private readonly Router $router)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw new HttpException(401, 'Necesitas iniciar sesión.');
        }

        if ($request->getMethod() === 'GET') {
            $request->session()->put('_intended', $request->getUri());
        }

        return Response::redirect($this->router->getRoute('login') !== null ? $this->router->url('login') : '/login');
    }
}
