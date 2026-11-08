<?php

/**
 * RememberMe.php
 *
 * Middleware de "recordarme": si no hay sesión pero llega la cookie, inicia la sesión del usuario (y
 * renueva la cookie); después de la respuesta escribe o borra la cookie que pidió auth().
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
use BootZen\Core\Config;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Va en el grupo web después de StartSession. Sin cookie y sin login con recordarme no toca la base.
 *
 * @example $app->middlewareGroup('web', [StartSession::class, RememberMe::class, VerifyCsrfToken::class]);
 */
final class RememberMe implements MiddlewareInterface
{
    public function __construct(private readonly Auth $auth, private readonly Config $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $name = $this->cookieName();
        $cookie = $request->getCookie($name);
        $cookie = is_string($cookie) && $cookie !== '' ? $cookie : null;
        $invalid = false;

        if ($cookie !== null && $this->auth->guest()) {
            $id = $this->auth->tokens()->consume($cookie);
            // La cookie ya no sirve (vencida, robada o de un usuario borrado): se borra
            $invalid = $id === null || $this->auth->loginUsingId($id, true) === null;
        }

        $response = $next($request);
        $queued = $this->auth->pullQueuedCookie();

        if ($queued === '' && $cookie !== null) {
            $this->auth->tokens()->forget($cookie);
        }

        if ($queued === '' || ($queued === null && $invalid)) {
            return $this->withCookie($response, $request, '', time() - 3600);
        }

        if ($queued !== null) {
            return $this->withCookie($response, $request, $queued, time() + $this->auth->tokens()->days() * 86400);
        }

        return $response;
    }

    private function withCookie(Response $response, Request $request, string $value, int $expires): Response
    {
        $secure = $this->config->get('session.secure');

        return $response->withCookie(
            $this->cookieName(),
            $value,
            $expires,
            '/',
            '',
            is_bool($secure) ? $secure : $request->isSecure(),
            true,
            'Lax'
        );
    }

    private function cookieName(): string
    {
        $name = $this->config->get('auth.remember_cookie', 'bootzen_remember');

        return is_string($name) && $name !== '' ? $name : 'bootzen_remember';
    }
}
