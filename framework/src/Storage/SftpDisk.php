<?php

/**
 * SftpDisk.php
 *
 * Driver 'sftp': un servidor SSH (VPS, almacenamiento con acceso SFTP). Usa la extensión ssh2 de PHP
 * (paquete php-ssh2 o pecl install ssh2). Acepta contraseña o llave privada y verifica la huella del
 * servidor si se configura 'fingerprint'.
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
use SensitiveParameter;

/**
 * Sin 'fingerprint' se confía en cualquier servidor que responda en esa dirección: configúralo en
 * producción (ssh-keyscan -t ed25519 host | ssh-keygen -lf - te da la huella, o la muestra el error).
 *
 * @example new SftpDisk('archivos.midominio.com', 'deploy', privateKey: '/home/app/.ssh/id_ed25519', root: 'archivos')
 */
final class SftpDisk implements Disk
{
    /**
     * @var resource|null
     */
    private $sftp = null;

    /**
     * Carpeta del usuario en el servidor: base de una raíz relativa.
     */
    private string $home = '/';

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        #[SensitiveParameter]
        private readonly ?string $password = null,
        private readonly ?string $privateKey = null,
        private readonly ?string $publicKey = null,
        #[SensitiveParameter]
        private readonly ?string $passphrase = null,
        private readonly int $port = 22,
        private readonly string $root = '',
        private readonly ?string $fingerprint = null,
        private readonly ?string $publicUrl = null
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @throws StorageException Si faltan host, username o una forma de autenticarse
     */
    public static function fromConfig(array $config, string $name): self
    {
        $value = static fn (string $key): ?string => is_scalar($config[$key] ?? null) && (string) $config[$key] !== '' ? (string) $config[$key] : null;
        $missing = array_filter(['host', 'username'], static fn (string $key): bool => $value($key) === null);

        if ($value('password') === null && $value('private_key') === null) {
            $missing[] = 'password o private_key';
        }

        if ($missing !== []) {
            throw new StorageException("Al disco {$name} (sftp) le falta: " . implode(', ', $missing) . '. Revisa las variables SFTP_* en .env.');
        }

        return new self(
            (string) $value('host'),
            (string) $value('username'),
            $value('password'),
            $value('private_key'),
            $value('public_key'),
            $value('passphrase'),
            (int) ($value('port') ?? 22),
            $value('root') ?? '',
            $value('fingerprint'),
            $value('url')
        );
    }

    public function put(string $path, string $contents, ?string $mime = null): void
    {
        $sftp = $this->sftp();
        $file = $this->full($path);
        $directory = dirname($file);

        if (Quietly::run(fn (): array|false => ssh2_sftp_stat($sftp, $directory))[0] === false) {
            Quietly::run(static fn (): bool => ssh2_sftp_mkdir($sftp, $directory, 0775, true));
        }

        $temporary = $directory . '/.' . basename($file) . '.' . bin2hex(random_bytes(4)) . '.part';
        [$written, $warning] = Quietly::run(fn (): int|false => file_put_contents($this->wrapper($temporary), $contents));

        if ($written === false) {
            throw new StorageException("No se pudo subir {$path} por SFTP" . ($warning !== '' ? " ({$warning})" : '') . '.');
        }

        // Algunos servidores no renombran sobre un archivo existente
        Quietly::run(static fn (): bool => ssh2_sftp_unlink($sftp, $file));

        if (Quietly::run(static fn (): bool => ssh2_sftp_rename($sftp, $temporary, $file))[0] !== true) {
            Quietly::run(static fn (): bool => ssh2_sftp_unlink($sftp, $temporary));

            throw new StorageException("No se pudo guardar {$path} por SFTP.");
        }
    }

    public function get(string $path): string
    {
        $this->sftp();
        [$contents] = Quietly::run(fn (): string|false => file_get_contents($this->wrapper($this->full($path))));

        if ($contents === false) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        return $contents;
    }

    public function exists(string $path): bool
    {
        return $this->stat($path) !== null;
    }

    public function delete(string $path): bool
    {
        if (! $this->exists($path)) {
            return false;
        }

        $sftp = $this->sftp();
        $file = $this->full($path);

        if (Quietly::run(static fn (): bool => ssh2_sftp_unlink($sftp, $file))[0] !== true) {
            throw new StorageException("No se pudo borrar {$path} por SFTP.");
        }

        return true;
    }

    public function size(string $path): int
    {
        $stat = $this->stat($path);

        if ($stat === null) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        return (int) ($stat['size'] ?? 0);
    }

    public function url(string $path): ?string
    {
        return $this->publicUrl === null ? null : rtrim($this->publicUrl, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * @return array<string|int, mixed>|null
     */
    private function stat(string $path): ?array
    {
        $sftp = $this->sftp();
        $file = $this->full($path);
        [$stat] = Quietly::run(static fn (): array|false => ssh2_sftp_stat($sftp, $file));

        return is_array($stat) ? $stat : null;
    }

    /**
     * @return resource
     */
    private function sftp()
    {
        if ($this->sftp !== null) {
            return $this->sftp;
        }

        if (! function_exists('ssh2_connect')) {
            throw new StorageException('El driver sftp necesita la extensión ssh2 de PHP (paquete php-ssh2, o pecl install ssh2).');
        }

        [$session, $warning] = Quietly::run(fn (): mixed => ssh2_connect($this->host, $this->port));

        if (! is_resource($session)) {
            throw new StorageException("No se pudo conectar al SFTP {$this->host}:{$this->port}" . ($warning !== '' ? " ({$warning})" : '') . '.');
        }

        $this->verifyFingerprint($session);

        [$authenticated] = $this->privateKey !== null
            ? Quietly::run(fn (): bool => ssh2_auth_pubkey_file($session, $this->username, $this->publicKey ?? $this->privateKey . '.pub', $this->privateKey, (string) $this->passphrase))
            : Quietly::run(fn (): bool => ssh2_auth_password($session, $this->username, (string) $this->password));

        if ($authenticated !== true) {
            throw new StorageException("El SFTP {$this->host} rechazó al usuario {$this->username}. Revisa la contraseña o la llave.");
        }

        [$sftp] = Quietly::run(static fn (): mixed => ssh2_sftp($session));

        if (! is_resource($sftp)) {
            throw new StorageException("El servidor {$this->host} no permite SFTP para {$this->username}.");
        }

        $home = Quietly::run(static fn (): string|false => ssh2_sftp_realpath($sftp, '.'))[0];
        $this->home = is_string($home) && $home !== '' ? $home : '/';

        return $this->sftp = $sftp;
    }

    /**
     * @param resource $session
     */
    private function verifyFingerprint($session): void
    {
        if ($this->fingerprint === null) {
            return;
        }

        $expected = strtolower((string) preg_replace('/[^a-fA-F0-9]/', '', $this->fingerprint));
        $md5 = strtolower((string) ssh2_fingerprint($session, SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX));
        $sha1 = strtolower((string) ssh2_fingerprint($session, SSH2_FINGERPRINT_SHA1 | SSH2_FINGERPRINT_HEX));

        if (! hash_equals($md5, $expected) && ! hash_equals($sha1, $expected)) {
            throw new StorageException("La huella del servidor SFTP {$this->host} no coincide con SFTP_FINGERPRINT (el servidor dice {$sha1}). No se envían credenciales.");
        }
    }

    private function wrapper(string $file): string
    {
        return 'ssh2.sftp://' . (int) $this->sftp . $file;
    }

    /**
     * Ruta absoluta en el servidor. Una raíz relativa (o vacía) parte de la carpeta del usuario.
     */
    private function full(string $path): string
    {
        $this->sftp();
        $base = str_starts_with($this->root, '/') ? $this->root : rtrim($this->home, '/') . '/' . $this->root;

        return '/' . trim(trim($base, '/') . '/' . $path, '/');
    }
}
