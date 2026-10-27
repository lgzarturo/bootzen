<?php

/**
 * RedirectIfAuthenticated.php
 *
 * Middleware 'guest': solo para invitados (login, registro). Si ya hay sesión, redirige a la página
 * de inicio configurada en config/auth.php (home).
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
use BootZen\Core\Config;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Va en rutas del grupo web: ->middleware('guest').
 */
final class RedirectIfAuthenticated implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth, private readonly Config $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if ($this->auth->guest()) {
            return $next($request);
        }

        $home = $this->config->get('auth.home', '/');

        return Response::redirect(is_string($home) ? $home : '/');
    }
}
