<?php

/**
 * Gate.php
 *
 * Permisos: qué puede hacer un usuario según su rol (config/auth.php, roles) y, para un registro
 * concreto, según la política del modelo (app/Policies/{Modelo}Policy.php).
 *
 * @package    BootZen\Auth
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.7.0
 */

declare(strict_types=1);

namespace BootZen\Auth;

use BootZen\Core\Container;
use BootZen\Core\HttpException;
use BootZen\Database\Model;

/**
 * Orden de la decisión:
 * 1. Sin usuario, no.
 * 2. Si su rol tiene '*', sí (el admin pasa todo, también las políticas).
 * 3. Si hay un modelo y su política tiene el método $ability, decide la política.
 * 4. Si no, decide la lista de permisos del rol ('tareas.*' cubre 'tareas.borrar').
 *
 * @example can('update', $tarea);         // TareaPolicy::update($user, $tarea)
 * @example can('reportes.ver');            // permisos del rol en config/auth.php
 * @example authorize('delete', $tarea);    // 403 si no puede
 */
final class Gate
{
    /**
     * @param array<string, list<string>> $roles Rol => permisos
     */
    public function __construct(
        private readonly array $roles,
        private readonly Container $container,
        private readonly string $policies = 'App\\Policies\\'
    ) {
    }

    /**
     * Indica si el usuario puede hacer $ability (sobre $subject, si se da).
     *
     * @param Model|class-string<Model>|null $subject Registro o clase del modelo (para create o viewAny)
     */
    public function allows(?Model $user, string $ability, Model|string|null $subject = null): bool
    {
        if ($user === null) {
            return false;
        }

        $permissions = $this->permissions($user);

        if (in_array('*', $permissions, true)) {
            return true;
        }

        $policy = $subject === null ? null : $this->policyFor($subject);

        if ($policy !== null && method_exists($policy, $ability)) {
            $result = $subject instanceof Model ? $policy->{$ability}($user, $subject) : $policy->{$ability}($user);

            return $result === true;
        }

        foreach ($permissions as $permission) {
            if ($permission === $ability || (str_ends_with($permission, '.*') && str_starts_with($ability, substr($permission, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lanza 403 (o $status, por ejemplo 404 para no revelar que el registro existe) si no puede.
     *
     * @param Model|class-string<Model>|null $subject
     * @throws HttpException
     */
    public function authorize(?Model $user, string $ability, Model|string|null $subject = null, int $status = 403): void
    {
        if (! $this->allows($user, $ability, $subject)) {
            throw new HttpException($status, $status === 404 ? '' : 'No tienes permiso para hacer esto.');
        }
    }

    /**
     * Indica si el usuario tiene alguno de los roles.
     */
    public function hasRole(?Model $user, string ...$roles): bool
    {
        return $user !== null && in_array(self::role($user), $roles, true);
    }

    /**
     * Roles definidos en config/auth.php.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return array_keys($this->roles);
    }

    /**
     * Permisos del rol del usuario.
     *
     * @return list<string>
     */
    public function permissions(Model $user): array
    {
        return $this->roles[self::role($user)] ?? [];
    }

    /**
     * Instancia de App\Policies\{Modelo}Policy o null si no existe.
     *
     * @param Model|class-string<Model> $subject
     */
    private function policyFor(Model|string $subject): ?object
    {
        $class = $subject instanceof Model ? $subject::class : $subject;
        $position = strrpos($class, '\\');
        $policy = $this->policies . ($position === false ? $class : substr($class, $position + 1)) . 'Policy';

        if (! class_exists($policy)) {
            return null;
        }

        $instance = $this->container->make($policy);

        return is_object($instance) ? $instance : null;
    }

    private static function role(Model $user): string
    {
        $role = $user->getAttribute('role');

        return is_string($role) ? $role : '';
    }
}
