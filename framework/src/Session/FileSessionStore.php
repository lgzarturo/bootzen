<?php

/**
 * FileSessionStore.php
 *
 * Sesiones guardadas como archivos en storage/sessions. Funcionan en cualquier hosting sin servicios extra.
 *
 * @package    BootZen\Session
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Session;

use RuntimeException;

/**
 * Un archivo por sesión. Los datos se serializan sin permitir objetos (solo escalares y arrays).
 */
final class FileSessionStore implements SessionStore
{
    /**
     * @param string $directory Carpeta de las sesiones
     * @param int $lifetime Segundos sin actividad tras los que una sesión expira
     */
    public function __construct(private readonly string $directory, private readonly int $lifetime = 7200)
    {
    }

    public function read(string $id): ?array
    {
        $file = $this->path($id);

        if (! is_file($file)) {
            return null;
        }

        if (filemtime($file) < time() - $this->lifetime) {
            @unlink($file);

            return null;
        }

        $contents = @file_get_contents($file);
        $data = $contents === false ? false : self::quietly(static fn (): mixed => unserialize($contents, ['allowed_classes' => false]));

        /** @var array<string, mixed>|null $result */
        $result = is_array($data) ? $data : null;

        return $result;
    }

    public function write(string $id, array $data): void
    {
        if (! is_dir($this->directory) && ! self::quietly(fn (): bool => mkdir($this->directory, 0775, true)) && ! is_dir($this->directory)) {
            throw new RuntimeException("No se pudo crear el directorio de sesiones {$this->directory}.");
        }

        $file = $this->path($id);
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temporary, serialize($data), LOCK_EX) === false || ! rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException("No se pudo guardar la sesión en {$this->directory}.");
        }
    }

    public function destroy(string $id): void
    {
        $file = $this->path($id);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function gc(int $lifetime): int
    {
        $deleted = 0;

        foreach (glob($this->directory . '/sess_*') ?: [] as $file) {
            if (filemtime($file) < time() - $lifetime && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Ejecuta sin convertir warnings en errores (datos corruptos, carpeta sin permisos).
     *
     * @template T
     * @param \Closure(): T $callback
     * @return T
     */
    private static function quietly(\Closure $callback): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    private function path(string $id): string
    {
        // El id ya viene validado por Session::isValidId(); basename evita salir de la carpeta igualmente
        return $this->directory . '/sess_' . basename($id);
    }
}
