<?php

/**
 * FtpDisk.php
 *
 * Driver 'ftp': un servidor FTP o FTPS (FTP con TLS), como el que dan muchos hostings. Usa la
 * extensión ftp de PHP. Si la carpeta también se sirve por web, 'url' es su dirección pública.
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
use FTP\Connection;
use SensitiveParameter;

/**
 * Abre una conexión la primera vez que se usa y la reutiliza durante la petición. Sube a un nombre
 * temporal y renombra al final, así nadie descarga un archivo a medias. FTPS cifra la conexión, pero
 * la extensión ftp de PHP no verifica el certificado del servidor: si eso importa, usa SFTP.
 *
 * @example new FtpDisk('ftp.mihosting.com', 'usuario', 'clave', root: 'archivos', ssl: true)
 */
final class FtpDisk implements Disk
{
    private ?Connection $connection = null;

    /**
     * Carpeta en la que entra el usuario al conectarse: base de una raíz relativa.
     */
    private string $home = '/';

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        #[SensitiveParameter]
        private readonly string $password,
        private readonly int $port = 21,
        private readonly string $root = '',
        private readonly bool $ssl = false,
        private readonly bool $passive = true,
        private readonly int $timeout = 30,
        private readonly ?string $publicUrl = null
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @throws StorageException Si faltan host, username o password
     */
    public static function fromConfig(array $config, string $name): self
    {
        $value = static fn (string $key): ?string => is_scalar($config[$key] ?? null) && (string) $config[$key] !== '' ? (string) $config[$key] : null;
        $missing = array_filter(['host', 'username', 'password'], static fn (string $key): bool => $value($key) === null);

        if ($missing !== []) {
            throw new StorageException("Al disco {$name} (ftp) le falta: " . implode(', ', $missing) . '. Revisa las variables FTP_* en .env.');
        }

        return new self(
            (string) $value('host'),
            (string) $value('username'),
            (string) $value('password'),
            (int) ($value('port') ?? 21),
            $value('root') ?? '',
            filter_var($config['ssl'] ?? false, FILTER_VALIDATE_BOOL),
            filter_var($config['passive'] ?? true, FILTER_VALIDATE_BOOL),
            (int) ($value('timeout') ?? 30),
            $value('url')
        );
    }

    public function put(string $path, string $contents, ?string $mime = null): void
    {
        $connection = $this->connection();
        $file = $this->full($path);
        $this->makeDirectory(dirname($file));

        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new StorageException('No se pudo preparar la subida.');
        }

        fwrite($stream, $contents);
        rewind($stream);
        $temporary = dirname($file) . '/.' . basename($file) . '.' . bin2hex(random_bytes(4)) . '.part';

        [$uploaded, $warning] = Quietly::run(static fn (): bool => ftp_fput($connection, $temporary, $stream, FTP_BINARY));
        fclose($stream);

        if ($uploaded !== true || Quietly::run(static fn (): bool => ftp_rename($connection, $temporary, $file))[0] !== true) {
            Quietly::run(static fn (): bool => ftp_delete($connection, $temporary));

            throw new StorageException("No se pudo subir {$path} por FTP" . ($warning !== '' ? " ({$warning})" : '') . '.');
        }
    }

    public function get(string $path): string
    {
        $connection = $this->connection();
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new StorageException('No se pudo preparar la descarga.');
        }

        $file = $this->full($path);
        [$downloaded] = Quietly::run(static fn (): bool => ftp_fget($connection, $stream, $file, FTP_BINARY));

        if ($downloaded !== true) {
            fclose($stream);

            throw new StorageException("No existe el archivo {$path}.");
        }

        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    public function exists(string $path): bool
    {
        return $this->remoteSize($path) >= 0;
    }

    public function delete(string $path): bool
    {
        if (! $this->exists($path)) {
            return false;
        }

        $connection = $this->connection();
        $file = $this->full($path);

        if (Quietly::run(static fn (): bool => ftp_delete($connection, $file))[0] !== true) {
            throw new StorageException("No se pudo borrar {$path} por FTP.");
        }

        return true;
    }

    public function size(string $path): int
    {
        $size = $this->remoteSize($path);

        if ($size < 0) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        return $size;
    }

    public function url(string $path): ?string
    {
        return $this->publicUrl === null ? null : rtrim($this->publicUrl, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public function __destruct()
    {
        if ($this->connection !== null) {
            $connection = $this->connection;
            Quietly::run(static fn (): bool => ftp_close($connection));
        }
    }

    private function remoteSize(string $path): int
    {
        $connection = $this->connection();
        $file = $this->full($path);

        return (int) Quietly::run(static fn (): int => ftp_size($connection, $file))[0];
    }

    private function connection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        if (! function_exists('ftp_connect')) {
            throw new StorageException('El driver ftp necesita la extensión ftp de PHP (en Docker: docker-php-ext-install ftp).');
        }

        if ($this->ssl && ! function_exists('ftp_ssl_connect')) {
            throw new StorageException('FTPS necesita PHP compilado con OpenSSL (ftp_ssl_connect).');
        }

        [$connection, $warning] = Quietly::run(fn (): Connection|false => $this->ssl
            ? ftp_ssl_connect($this->host, $this->port, $this->timeout)
            : ftp_connect($this->host, $this->port, $this->timeout));

        if (! $connection instanceof Connection) {
            throw new StorageException("No se pudo conectar al FTP {$this->host}:{$this->port}" . ($warning !== '' ? " ({$warning})" : '') . '.');
        }

        if (Quietly::run(fn (): bool => ftp_login($connection, $this->username, $this->password))[0] !== true) {
            Quietly::run(static fn (): bool => ftp_close($connection));

            throw new StorageException("El FTP {$this->host} rechazó el usuario o la contraseña. Revisa FTP_USERNAME y FTP_PASSWORD.");
        }

        Quietly::run(fn (): bool => ftp_pasv($connection, $this->passive));
        $home = Quietly::run(static fn (): string|false => ftp_pwd($connection))[0];
        $this->home = is_string($home) && $home !== '' ? $home : '/';

        return $this->connection = $connection;
    }

    private function makeDirectory(string $directory): void
    {
        $connection = $this->connection();
        $current = '';

        foreach (array_filter(explode('/', $directory), static fn (string $part): bool => $part !== '') as $part) {
            $current .= '/' . $part;

            if (Quietly::run(static fn (): bool => ftp_chdir($connection, $current))[0] !== true) {
                Quietly::run(static fn (): string|false => ftp_mkdir($connection, $current));
            }
        }

        $home = $this->home;
        Quietly::run(static fn (): bool => ftp_chdir($connection, $home));
    }

    /**
     * Ruta absoluta en el servidor. Una raíz relativa (o vacía) parte de la carpeta del usuario,
     * así funciona igual en servidores que encierran al usuario en su carpeta y en los que no.
     */
    private function full(string $path): string
    {
        $this->connection();
        $base = str_starts_with($this->root, '/') ? $this->root : rtrim($this->home, '/') . '/' . $this->root;

        return '/' . trim(trim($base, '/') . '/' . $path, '/');
    }
}
