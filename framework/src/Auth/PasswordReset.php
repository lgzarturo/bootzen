<?php

/**
 * PasswordReset.php
 *
 * Tokens para recuperar la contraseña. En la base solo se guarda el hash del token, nunca el token.
 *
 * @package    BootZen\Auth
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Auth;

use BootZen\Database\Connection;
use DateTimeImmutable;

/**
 * Necesita la tabla password_resets (email, token, created_at) que crea la migración del scaffold --auth.
 *
 * @example $token = $resets->create($email); // se envía por correo en un enlace
 * @example $resets->isValid($email, $token);
 */
final class PasswordReset
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $table = 'password_resets',
        private readonly int $expireMinutes = 60
    ) {
    }

    /**
     * Crea un token nuevo para el correo (reemplaza al anterior) y lo devuelve en claro.
     */
    public function create(string $email): string
    {
        $token = bin2hex(random_bytes(32));

        $this->db->transaction(function (Connection $db) use ($email, $token): void {
            $db->table($this->table)->where('email', $email)->delete();
            $db->table($this->table)->insert([
                'email' => $email,
                'token' => hash('sha256', $token),
                'created_at' => new DateTimeImmutable(),
            ]);
        });

        return $token;
    }

    /**
     * Indica si el token corresponde al correo y no expiró.
     */
    public function isValid(string $email, string $token): bool
    {
        $row = $this->db->table($this->table)->where('email', $email)->first();

        if ($row === null || ! is_string($row['token'] ?? null) || ! is_string($row['created_at'] ?? null)) {
            return false;
        }

        $created = strtotime($row['created_at']);

        return hash_equals($row['token'], hash('sha256', $token))
            && $created !== false
            && $created >= time() - $this->expireMinutes * 60;
    }

    /**
     * Borra el token del correo (después de usarlo).
     */
    public function delete(string $email): void
    {
        $this->db->table($this->table)->where('email', $email)->delete();
    }
}
