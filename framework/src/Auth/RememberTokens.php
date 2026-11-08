<?php

/**
 * RememberTokens.php
 *
 * Tokens de "recordarme" en la tabla remember_tokens: uno por dispositivo, con selector y validador.
 *
 * @package    BootZen\Auth
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.6.0
 */

declare(strict_types=1);

namespace BootZen\Auth;

use BootZen\Database\Connection;
use DateTimeImmutable;

/**
 * La cookie lleva "selector:validador". En la base solo queda el hash del validador, así una copia de
 * la base no sirve para entrar. Cada uso borra el token y emite otro; si llega un selector conocido
 * con un validador equivocado (la cookie se copió y la otra copia ya se usó), se borran todos los
 * tokens del usuario.
 *
 * @internal Lo usan Auth y el middleware RememberMe.
 */
final class RememberTokens
{
    public function __construct(
        private readonly Connection $db,
        private readonly int $days = 30,
        private readonly string $table = 'remember_tokens'
    ) {
    }

    /**
     * Crea un token para el usuario y devuelve el valor de la cookie.
     */
    public function issue(int|string $userId): string
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $this->db->table($this->table)->insert([
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'user_id' => $userId,
            'expires_at' => (new DateTimeImmutable())->modify("+{$this->days} days")->format('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // De vez en cuando limpia los vencidos
        if (random_int(1, 50) === 1) {
            $this->db->table($this->table)->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
        }

        return $selector . ':' . $validator;
    }

    /**
     * Valida la cookie y la gasta: devuelve el id del usuario (y borra el token) o null.
     */
    public function consume(string $cookie): int|string|null
    {
        [$selector, $validator] = self::split($cookie);

        if ($selector === null) {
            return null;
        }

        $row = $this->db->table($this->table)->where('selector', $selector)->first();

        if ($row === null) {
            return null;
        }

        $userId = $row['user_id'];
        $userId = is_int($userId) || is_string($userId) ? $userId : null;

        if (! hash_equals((string) $row['validator_hash'], hash('sha256', (string) $validator))) {
            // Selector bueno con validador malo: alguien tiene una copia de la cookie
            if ($userId !== null) {
                $this->forgetUser($userId);
            }

            return null;
        }

        $this->db->table($this->table)->where('selector', $selector)->delete();

        return (string) $row['expires_at'] >= date('Y-m-d H:i:s') ? $userId : null;
    }

    /**
     * Borra el token de esta cookie (cerrar sesión en este dispositivo).
     */
    public function forget(string $cookie): void
    {
        [$selector] = self::split($cookie);

        if ($selector !== null) {
            $this->db->table($this->table)->where('selector', $selector)->delete();
        }
    }

    /**
     * Borra todos los tokens del usuario (cerrar sesión en todos los dispositivos).
     */
    public function forgetUser(int|string $userId): void
    {
        $this->db->table($this->table)->where('user_id', $userId)->delete();
    }

    /**
     * Días que dura un token.
     */
    public function days(): int
    {
        return $this->days;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function split(string $cookie): array
    {
        return preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $match) === 1 ? [$match[1], $match[2]] : [null, null];
    }
}
