<?php

/**
 * SessionStore.php
 *
 * Contrato de los almacenes de sesión (archivos en storage/sessions o memoria para tests).
 *
 * @package    BootZen\Session
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Session;

/**
 * Lee y escribe los datos de una sesión por su id.
 */
interface SessionStore
{
    /**
     * Datos de la sesión, o null si no existe o ya expiró.
     *
     * @return array<string, mixed>|null
     */
    public function read(string $id): ?array;

    /**
     * @param array<string, mixed> $data
     */
    public function write(string $id, array $data): void;

    public function destroy(string $id): void;

    /**
     * Borra las sesiones sin actividad en los últimos $lifetime segundos. Devuelve cuántas borró.
     */
    public function gc(int $lifetime): int;
}
