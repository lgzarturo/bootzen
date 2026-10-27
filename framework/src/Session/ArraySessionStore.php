<?php

/**
 * ArraySessionStore.php
 *
 * Sesiones en memoria. Para tests: no escribe nada en disco y desaparece con el proceso.
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
 * Almacén en memoria (SESSION_DRIVER=array).
 */
final class ArraySessionStore implements SessionStore
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sessions = [];

    public function read(string $id): ?array
    {
        return $this->sessions[$id] ?? null;
    }

    public function write(string $id, array $data): void
    {
        $this->sessions[$id] = $data;
    }

    public function destroy(string $id): void
    {
        unset($this->sessions[$id]);
    }

    public function gc(int $lifetime): int
    {
        return 0;
    }
}
