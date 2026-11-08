<?php

/**
 * RequirePermission.php
 *
 * Middleware 'can': solo deja pasar a usuarios cuyo rol tiene el permiso. Va después de 'auth'.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.7.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Auth\Auth;
use BootZen\Auth\Gate;
use BootZen\Core\HttpException;
use BootZen\Core\ParameterizedMiddleware;
use BootZen\Core\Request;
use BootZen\Core\Response;
use InvalidArgumentException;

/**
 * Revisa permisos del rol, no políticas (para un registro concreto usa authorize() en el controlador).
 *
 * @example ->middleware('can:reportes.ver')
 */
final class RequirePermission implements ParameterizedMiddleware
{
    public function __construct(private readonly Auth $auth, private readonly Gate $gate, private readonly string $permission = '')
    {
    }

    public function withParameters(array $parameters): static
    {
        $permission = trim($parameters[0] ?? '');

        if ($permission === '') {
            throw new InvalidArgumentException("El middleware can necesita un permiso: ->middleware('can:reportes.ver').");
        }

        return new static($this->auth, $this->gate, $permission);
    }

    public function process(Request $request, callable $next): Response
    {
        if (! $this->gate->allows($this->auth->user(), $this->permission)) {
            throw new HttpException(403, 'No tienes permiso para hacer esto.');
        }

        return $next($request);
    }
}
