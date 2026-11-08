<?php

/**
 * RequireRole.php
 *
 * Middleware 'role': solo deja pasar a usuarios con alguno de los roles. Va después de 'auth'.
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
 * Responde 403 a quien no tiene el rol. Un rol con permiso '*' (admin) también pasa.
 *
 * @example $router->group(['middleware' => ['auth', 'role:admin']], ...);
 * @example ->middleware('role:admin,editor')
 */
final class RequireRole implements ParameterizedMiddleware
{
    /**
     * @param list<string> $roles
     */
    public function __construct(private readonly Auth $auth, private readonly Gate $gate, private readonly array $roles = [])
    {
    }

    public function withParameters(array $parameters): static
    {
        $roles = array_values(array_filter(array_map('trim', $parameters), static fn (string $role): bool => $role !== ''));

        if ($roles === []) {
            throw new InvalidArgumentException("El middleware role necesita al menos un rol: ->middleware('role:admin').");
        }

        return new static($this->auth, $this->gate, $roles);
    }

    public function process(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if (! $this->gate->hasRole($user, ...$this->roles) && ! ($user !== null && in_array('*', $this->gate->permissions($user), true))) {
            throw new HttpException(403, 'No tienes permiso para hacer esto.');
        }

        return $next($request);
    }
}
