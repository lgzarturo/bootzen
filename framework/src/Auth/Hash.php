<?php

/**
 * Hash.php
 *
 * Hash de contraseñas con password_hash: Argon2id si PHP lo trae, bcrypt si no.
 *
 * @package    BootZen\Auth
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Auth;

/**
 * @example $usuario->password = Hash::make($datos['password']);
 * @example Hash::check($texto, $usuario->password);
 */
final class Hash
{
    public static function make(string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    public static function check(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }

    /**
     * Indica si el hash se hizo con otro algoritmo u opciones y conviene rehacerlo.
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    private static function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
