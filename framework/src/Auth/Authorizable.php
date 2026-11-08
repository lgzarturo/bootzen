<?php

/**
 * Authorizable.php
 *
 * Trait para el modelo de usuario: $user->can('update', $tarea) y $user->hasRole('admin').
 *
 * @package    BootZen\Auth
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.7.0
 */

declare(strict_types=1);

namespace BootZen\Auth;

use BootZen\Core\Application;
use BootZen\Database\Model;
use LogicException;

/**
 * Usa la columna role y los permisos de config/auth.php (roles).
 *
 * @example final class User extends Model { use Authorizable; }
 */
trait Authorizable
{
    /**
     * Indica si este usuario puede hacer $ability (sobre $subject, si se da).
     *
     * @param Model|class-string<Model>|null $subject
     */
    public function can(string $ability, Model|string|null $subject = null): bool
    {
        return self::gate()->allows($this, $ability, $subject);
    }

    /**
     * Indica si tiene alguno de los roles.
     */
    public function hasRole(string ...$roles): bool
    {
        return self::gate()->hasRole($this, ...$roles);
    }

    private static function gate(): Gate
    {
        $gate = Application::getInstance()->make(Gate::class);

        return $gate instanceof Gate ? $gate : throw new LogicException('El servicio Gate no es válido.');
    }
}
