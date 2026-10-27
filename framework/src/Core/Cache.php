<?php

/**
 * Cache.php
 *
 * Caché en archivos para BootZen. Cada clave se guarda en storage/cache/data con su expiración.
 * Funciona en cualquier hosting sin Redis ni extensiones adicionales.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use Closure;
use RuntimeException;

/**
 * Caché clave/valor persistida en archivos.
 *
 * Los valores se serializan con serialize(), así que admite escalares, arrays y objetos serializables.
 * Sin directorio (null) guarda en memoria: es el driver 'array' que usan los tests.
 *
 * @example $stats = $cache->remember('dashboard.stats', 600, fn () => $service->stats());
 */
final class Cache
{
    /**
     * Entradas en memoria cuando no hay directorio.
     *
     * @var array<string, string>
     */
    private array $memory = [];

    /**
     * @param string|null $directory Directorio de los archivos de caché; null para guardar en memoria
     */
    public function __construct(private readonly ?string $directory)
    {
    }

    /**
     * Obtiene un valor o el valor por defecto si no existe o expiró.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->read($key);

        return $entry === null ? $default : $entry['value'];
    }

    /**
     * Guarda un valor.
     *
     * @param int|null $ttl Segundos de vida; null para no expirar
     * @throws RuntimeException Si no se puede escribir en el directorio de caché
     */
    public function set(string $key, mixed $value, ?int $ttl = 3600): void
    {
        $payload = serialize([
            'expires' => $ttl === null ? null : time() + $ttl,
            'value' => $value,
        ]);

        if ($this->directory === null) {
            $this->memory[$key] = $payload;

            return;
        }

        $this->ensureDirectory($this->directory);
        $file = $this->path($key);
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temporary, $payload, LOCK_EX) === false || ! rename($temporary, $file)) {
            @unlink($temporary);

            throw new RuntimeException("No se pudo escribir la caché en {$this->directory}.");
        }
    }

    /**
     * Indica si existe un valor vigente para la clave.
     */
    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    /**
     * Elimina una clave.
     */
    public function forget(string $key): void
    {
        if ($this->directory === null) {
            unset($this->memory[$key]);

            return;
        }

        $file = $this->path($key);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Devuelve el valor cacheado o lo calcula, lo guarda y lo devuelve.
     *
     * @template T
     * @param Closure(): T $callback Calcula el valor si no está en caché
     * @param int|null $ttl Segundos de vida; null para no expirar
     * @return T
     */
    public function remember(string $key, ?int $ttl, Closure $callback): mixed
    {
        $entry = $this->read($key);
        if ($entry !== null) {
            return $entry['value'];
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Incrementa un contador y devuelve el nuevo valor. Conserva la expiración existente.
     *
     * @param int|null $ttl Segundos de vida si la clave no existía
     */
    public function increment(string $key, int $by = 1, ?int $ttl = null): int
    {
        $entry = $this->read($key);
        $current = $entry !== null && is_int($entry['value']) ? $entry['value'] : 0;
        $remaining = $entry !== null && $entry['expires'] !== null ? max(1, $entry['expires'] - time()) : $ttl;

        $this->set($key, $current + $by, $remaining);

        return $current + $by;
    }

    /**
     * Elimina todas las claves.
     */
    public function flush(): void
    {
        $this->memory = [];

        if ($this->directory === null) {
            return;
        }

        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * Lee una entrada vigente; borra el archivo si expiró o está corrupto.
     *
     * @return array{expires: int|null, value: mixed}|null
     */
    private function read(string $key): ?array
    {
        if ($this->directory === null) {
            $contents = $this->memory[$key] ?? null;
        } else {
            $file = $this->path($key);
            $contents = is_file($file) ? @file_get_contents($file) : null;
        }

        if ($contents === null) {
            return null;
        }

        $entry = $contents === false ? false : self::unserializeQuietly($contents);

        if (! is_array($entry) || ! array_key_exists('value', $entry) || ! array_key_exists('expires', $entry)) {
            $this->forget($key);

            return null;
        }

        $expires = is_int($entry['expires']) ? $entry['expires'] : null;
        if ($expires !== null && $expires <= time()) {
            $this->forget($key);

            return null;
        }

        return ['expires' => $expires, 'value' => $entry['value']];
    }

    /**
     * Deserializa sin emitir warnings si el contenido está corrupto (devuelve false).
     */
    private static function unserializeQuietly(string $contents): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return unserialize($contents);
        } finally {
            restore_error_handler();
        }
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.cache';
    }

    /**
     * @throws RuntimeException Si el directorio no existe y no se puede crear
     */
    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio de caché {$directory}.");
        }
    }
}
