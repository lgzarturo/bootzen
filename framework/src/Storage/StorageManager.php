<?php

/**
 * StorageManager.php
 *
 * Crea los discos de config/storage.php a pedido y sabe cuál es el privado por defecto
 * (STORAGE_DISK) y cuál el público (STORAGE_PUBLIC_DISK).
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

/**
 * Cada disco lleva 'driver' (local, s3, ftp o sftp), 'visibility' (public o private) y un 'prefix'
 * opcional (carpeta dentro del disco). El resto depende del driver. En la app se usa a través de
 * storage(), file_url() y temporary_url().
 */
final class StorageManager
{
    /**
     * @var array<string, Storage>
     */
    private array $disks = [];

    /**
     * @param array<string, mixed> $config config('storage')
     * @param string $basePath Raíz del proyecto, para las rutas locales relativas
     * @param string $urlBase Ruta de APP_URL ('' o '/tienda'), base de las URLs del disco público local
     */
    public function __construct(
        private readonly array $config,
        private readonly string $basePath,
        private readonly ?UrlSigner $signer = null,
        private readonly string $urlBase = ''
    ) {
    }

    /**
     * Disco por nombre; sin nombre, el privado por defecto.
     *
     * @throws StorageException Si el disco no está configurado o le faltan datos
     */
    public function disk(?string $name = null): Storage
    {
        $name ??= is_string($this->config['default'] ?? null) ? $this->config['default'] : 'local';

        return $this->disks[$name] ??= $this->build($name);
    }

    /**
     * El disco público por defecto (donde guarda storePublic()).
     */
    public function publicDisk(): Storage
    {
        return $this->disk(is_string($this->config['public'] ?? null) ? $this->config['public'] : 'public');
    }

    /**
     * Nombres de los discos configurados.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys((is_array($this->config['disks'] ?? null) ? $this->config['disks'] : []) + self::DEFAULTS);
    }

    /**
     * Discos que existen aunque no estén en config/storage.php (proyectos creados antes de la 2.3).
     */
    private const DEFAULTS = [
        'local' => ['driver' => 'local', 'root' => 'storage/app', 'visibility' => 'private'],
        'public' => ['driver' => 'local', 'root' => 'public', 'visibility' => 'public'],
    ];

    private function build(string $name): Storage
    {
        $disks = (is_array($this->config['disks'] ?? null) ? $this->config['disks'] : []) + self::DEFAULTS;
        $config = $disks[$name] ?? null;

        if (! is_array($config)) {
            throw new StorageException("El disco [{$name}] no está en config/storage.php. Discos: " . implode(', ', array_keys($disks)) . '.');
        }

        $public = ($config['visibility'] ?? 'private') === 'public';
        $driver = $config['driver'] ?? 'local';

        $disk = match ($driver) {
            'local' => new LocalDisk(
                $this->path((string) ($config['root'] ?? 'storage/app')),
                $public ? (self::string($config['url'] ?? null) ?? rtrim($this->urlBase, '/')) : null
            ),
            's3' => S3Disk::fromConfig($config, $name),
            'ftp' => FtpDisk::fromConfig($config, $name),
            'sftp' => SftpDisk::fromConfig($config, $name),
            default => throw new StorageException("Driver [" . (is_scalar($driver) ? (string) $driver : '?') . "] desconocido en el disco {$name}: usa local, s3, ftp o sftp."),
        };

        return new Storage($name, $disk, $public, trim((string) (self::string($config['prefix'] ?? null) ?? ''), '/'), $this->signer);
    }

    private function path(string $root): string
    {
        return str_starts_with($root, '/') ? $root : rtrim($this->basePath, '/') . '/' . $root;
    }

    private static function string(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
