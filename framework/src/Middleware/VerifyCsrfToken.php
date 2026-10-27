<?php

/**
 * VerifyCsrfToken.php
 *
 * Protección CSRF: las peticiones que modifican datos deben enviar el token de la sesión en el campo
 * _token (lo agrega @csrf) o en la cabecera X-CSRF-TOKEN (la agrega bootzen.js). Si no coincide, 419.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Core\Config;
use BootZen\Core\HttpException;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Middleware CSRF (grupo web). Va después de StartSession.
 *
 * Con APP_ENV=testing no verifica nada, así los tests envían formularios sin pedir un token antes.
 * Las rutas de config session.csrf_except (admite *) tampoco se verifican, por ejemplo webhooks.
 */
final class VerifyCsrfToken implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly Config $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (
            in_array($request->getMethod(), self::SAFE_METHODS, true)
            || $this->config->get('app.env') === 'testing'
            || $this->isExcluded($request)
        ) {
            return $next($request);
        }

        $token = $request->getBody('_token') ?? $request->getHeader('x-csrf-token');

        if (! is_string($token) || $token === '' || ! hash_equals($request->session()->token(), $token)) {
            throw new HttpException(419, 'La sesión expiró. Recarga la página e inténtalo de nuevo.');
        }

        return $next($request);
    }

    private function isExcluded(Request $request): bool
    {
        $except = $this->config->get('session.csrf_except', []);

        foreach (is_array($except) ? $except : [] as $pattern) {
            if (is_string($pattern) && fnmatch('/' . ltrim($pattern, '/'), $request->getPath())) {
                return true;
            }
        }

        return false;
    }
}
