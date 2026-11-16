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
     * Candados tomados por este proceso: número => [archivo abierto, veces tomado].
     *
     * @var array<int, array{resource, int}>
     */
    private array $locks = [];

    /**
     * Cuántos archivos de candado se reparten entre todas las claves.
     */
    private const LOCK_SLOTS = 64;

    /**
     * @param string|null $directory Directorio de los archivos de caché; null para guardar en memoria
     * @param string|null $key Clave (APP_KEY) para firmar cada archivo: uno que no lleve la firma se descarta
     *                         sin deserializarlo
     */
    public function __construct(
        private readonly ?string $directory,
        #[\SensitiveParameter]
        private readonly ?string $key = null
    ) {
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

        if ($this->key !== null) {
            $payload = $this->signature($payload) . $payload;
        }

        $this->ensureDirectory($this->directory);
        $file = $this->path($key);
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // 0600: los contadores y lo que guarde la app no los lee otro usuario del servidor
        $written = file_put_contents($temporary, $payload, LOCK_EX) !== false;
        @chmod($temporary, 0600);

        if (! $written || ! rename($temporary, $file)) {
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
     * Incrementa un contador y devuelve el nuevo valor. Conserva la expiración existente. Es atómico:
     * dos peticiones a la vez no leen el mismo valor.
     *
     * @param int|null $ttl Segundos de vida si la clave no existía
     */
    public function increment(string $key, int $by = 1, ?int $ttl = null): int
    {
        return $this->atomic($key, function () use ($key, $by, $ttl): int {
            $entry = $this->read($key);
            $current = $entry !== null && is_int($entry['value']) ? $entry['value'] : 0;
            $remaining = $entry !== null && $entry['expires'] !== null ? max(1, $entry['expires'] - time()) : $ttl;

            $this->set($key, $current + $by, $remaining);

            return $current + $by;
        });
    }

    /**
     * Ejecuta $callback con un candado exclusivo sobre la clave: mientras corre, ningún otro proceso entra
     * a atomic() ni a increment() con esa clave. Sirve para "leer, decidir y escribir" sin carreras.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     * @example $cache->atomic('stock:5', fn () => $cache->set('stock:5', $cache->get('stock:5', 0) - 1));
     */
    public function atomic(string $key, Closure $callback): mixed
    {
        if ($this->directory === null) {
            return $callback();
        }

        $slot = abs(crc32($key)) % self::LOCK_SLOTS;

        if (isset($this->locks[$slot])) {
            // El mismo proceso ya lo tiene (increment() dentro de atomic()): no se vuelve a pedir
            $this->locks[$slot][1]++;
        } else {
            $this->ensureDirectory($this->directory);
            $handle = @fopen($this->directory . '/.lock-' . $slot, 'c');

            if ($handle === false || ! flock($handle, LOCK_EX)) {
                throw new RuntimeException("No se pudo tomar el candado de la caché en {$this->directory}.");
            }

            $this->locks[$slot] = [$handle, 1];
        }

        try {
            return $callback();
        } finally {
            if (--$this->locks[$slot][1] === 0) {
                flock($this->locks[$slot][0], LOCK_UN);
                fclose($this->locks[$slot][0]);
                unset($this->locks[$slot]);
            }
        }
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

        if ($contents !== false && $this->directory !== null && $this->key !== null) {
            // Sin firma válida no se deserializa: el archivo no lo escribió esta aplicación
            $payload = substr($contents, 64);
            $contents = strlen($contents) > 64 && hash_equals($this->signature($payload), substr($contents, 0, 64)) ? $payload : false;
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

    private function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, 'bootzen-cache|' . $this->key);
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
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio de caché {$directory}.");
        }
    }
}
