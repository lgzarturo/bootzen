<?php

/**
 * LocalDisk.php
 *
 * Driver 'local': una carpeta del servidor. Con $url es público (por ejemplo public/ servido por la
 * web); sin $url es privado (storage/app) y se entrega con temporaryUrl() o download().
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

use BootZen\Support\Quietly;

/**
 * Las escrituras van a un archivo temporal y se renombran al final: nadie lee un archivo a medias.
 */
final class LocalDisk implements Disk
{
    public function __construct(private readonly string $root, private readonly ?string $url = null)
    {
    }

    public function put(string $path, string $contents, ?string $mime = null): void
    {
        $file = $this->full($path);
        $directory = dirname($file);

        if (! is_dir($directory)) {
            Quietly::run(static fn (): bool => mkdir($directory, 0775, true));

            if (! is_dir($directory)) {
                throw new StorageException("No se pudo crear la carpeta para {$path}: revisa los permisos de {$this->root}.");
            }
        }

        $temporary = $directory . '/.' . basename($file) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        [$saved] = Quietly::run(static fn (): bool => file_put_contents($temporary, $contents) !== false && rename($temporary, $file));

        if ($saved !== true) {
            Quietly::run(static fn (): bool => ! is_file($temporary) || unlink($temporary));

            throw new StorageException("No se pudo guardar {$path}: revisa los permisos de {$this->root}.");
        }
    }

    public function get(string $path): string
    {
        $file = $this->full($path);
        [$contents] = is_file($file) ? Quietly::run(static fn (): string|false => file_get_contents($file)) : [false];

        if ($contents === false) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        return $contents;
    }

    public function exists(string $path): bool
    {
        return is_file($this->full($path));
    }

    public function delete(string $path): bool
    {
        $file = $this->full($path);

        if (! is_file($file)) {
            return false;
        }

        if (Quietly::run(static fn (): bool => unlink($file))[0] !== true) {
            throw new StorageException("No se pudo borrar {$path}.");
        }

        return true;
    }

    public function size(string $path): int
    {
        $size = is_file($this->full($path)) ? filesize($this->full($path)) : false;

        if ($size === false) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        return $size;
    }

    public function url(string $path): ?string
    {
        return $this->url === null ? null : rtrim($this->url, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function full(string $path): string
    {
        return rtrim($this->root, '/') . '/' . $path;
    }
}
