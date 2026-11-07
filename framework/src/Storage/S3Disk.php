<?php

/**
 * S3Disk.php
 *
 * Driver 's3': AWS S3 y cualquier servicio compatible (Cloudflare R2, DigitalOcean Spaces,
 * Backblaze B2, Wasabi, MinIO, Google Cloud Storage en modo interoperable). Sin SDK: peticiones HTTP
 * con streams de PHP y firma SigV4 propia.
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
use DateTimeImmutable;
use SensitiveParameter;

/**
 * La visibilidad pública depende del bucket (política o dominio público del proveedor); este driver
 * solo arma las URLs. Con 'acl' => 'public-read' manda esa ACL al subir, para los servicios que la usan.
 *
 * @example new S3Disk('mi-bucket', 'auto', 'CLAVE', 'SECRETO', 'https://<cuenta>.r2.cloudflarestorage.com')
 */
final class S3Disk implements Disk, TemporaryUrls
{
    private readonly S3Signer $signer;

    private readonly string $scheme;

    private readonly string $host;

    public function __construct(
        private readonly string $bucket,
        string $region,
        string $key,
        #[SensitiveParameter]
        string $secret,
        ?string $endpoint = null,
        private readonly bool $pathStyle = false,
        private readonly ?string $publicUrl = null,
        private readonly ?string $acl = null,
        private readonly float $timeout = 30.0
    ) {
        $endpoint ??= $region === 'us-east-1' ? 'https://s3.amazonaws.com' : "https://s3.{$region}.amazonaws.com";
        $parts = parse_url($endpoint);

        if (! is_array($parts) || ! isset($parts['host'])) {
            throw new StorageException("S3_ENDPOINT inválido [{$endpoint}]: usa por ejemplo https://s3.us-east-1.amazonaws.com.");
        }

        $this->scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $this->host = $pathStyle ? $host : "{$bucket}.{$host}";
        $this->signer = new S3Signer($key, $secret, $region);
    }

    /**
     * Crea el driver desde la configuración del disco.
     *
     * @param array<string, mixed> $config
     * @throws StorageException Si faltan bucket, key o secret
     */
    public static function fromConfig(array $config, string $name): self
    {
        $value = static fn (string $key): ?string => is_scalar($config[$key] ?? null) && (string) $config[$key] !== '' ? (string) $config[$key] : null;
        $missing = array_filter(['bucket', 'key', 'secret'], static fn (string $key): bool => $value($key) === null);

        if ($missing !== []) {
            throw new StorageException("Al disco {$name} (s3) le falta: " . implode(', ', $missing) . '. Revisa las variables S3_* en .env.');
        }

        return new self(
            (string) $value('bucket'),
            $value('region') ?? 'us-east-1',
            (string) $value('key'),
            (string) $value('secret'),
            $value('endpoint'),
            filter_var($config['path_style'] ?? false, FILTER_VALIDATE_BOOL),
            $value('url'),
            $value('acl'),
            (float) ($value('timeout') ?? 30)
        );
    }

    public function put(string $path, string $contents, ?string $mime = null): void
    {
        $headers = ['content-type' => $mime ?? 'application/octet-stream', 'content-length' => (string) strlen($contents)];

        if ($this->acl !== null) {
            $headers['x-amz-acl'] = $this->acl;
        }

        $this->request('PUT', $path, $headers, $contents, [200], "subir {$path}");
    }

    public function get(string $path): string
    {
        [$status, $body] = $this->request('GET', $path, [], '', [200, 404], "leer {$path}");

        if ($status === 404) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        return $body;
    }

    public function exists(string $path): bool
    {
        return $this->request('HEAD', $path, [], '', [200, 404], "revisar {$path}")[0] === 200;
    }

    public function delete(string $path): bool
    {
        if (! $this->exists($path)) {
            return false;
        }

        $this->request('DELETE', $path, [], '', [200, 204], "borrar {$path}");

        return true;
    }

    public function size(string $path): int
    {
        [$status, , $headers] = $this->request('HEAD', $path, [], '', [200, 404], "revisar {$path}");

        if ($status === 404) {
            throw new StorageException("No existe el archivo {$path}.");
        }

        return (int) ($headers['content-length'] ?? 0);
    }

    public function url(string $path): string
    {
        $key = self::encode($path);

        return $this->publicUrl !== null
            ? rtrim($this->publicUrl, '/') . '/' . $key
            : "{$this->scheme}://{$this->host}{$this->uri($path)}";
    }

    public function temporaryUrl(string $path, int $expires, ?string $downloadName = null): string
    {
        $seconds = max(1, min(604800, $expires - time()));
        $extra = $downloadName === null ? [] : [
            'response-content-disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) . "\"; filename*=UTF-8''" . rawurlencode($downloadName),
        ];
        $query = $this->signer->presign('GET', $this->host, $this->uri($path), $seconds, new DateTimeImmutable(), $extra);

        return "{$this->scheme}://{$this->host}{$this->uri($path)}?{$query}";
    }

    /**
     * Ejecuta una petición firmada.
     *
     * @param array<string, string> $headers
     * @param list<int> $expected
     * @return array{int, string, array<string, string>}
     */
    private function request(string $method, string $path, array $headers, string $body, array $expected, string $action): array
    {
        $uri = $this->uri($path);
        $signed = $this->signer->sign($method, $uri, [], ['host' => $this->host] + $headers, hash('sha256', $body), new DateTimeImmutable());
        unset($signed['host']);

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", array_map(static fn (string $name, string $value): string => "{$name}: {$value}", array_keys($signed), $signed)),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => $this->timeout,
                'protocol_version' => 1.1,
            ],
        ]);

        $rawHeaders = [];
        [$content, $warning] = Quietly::run(function () use ($uri, $context, &$rawHeaders): string|false {
            $result = file_get_contents("{$this->scheme}://{$this->host}{$uri}", false, $context);
            $rawHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : $http_response_header;

            return $result;
        });

        if ($content === false || $rawHeaders === []) {
            throw new StorageException("No se pudo conectar con {$this->host} para {$action}" . ($warning !== '' ? " ({$warning})" : '') . '.');
        }

        $status = 0;
        $responseHeaders = [];
        foreach ($rawHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
                $status = (int) $match[1];
                $responseHeaders = [];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        if (! in_array($status, $expected, true)) {
            preg_match('#<Code>([^<]+)</Code>#', $content, $code);
            preg_match('#<Message>([^<]+)</Message>#', $content, $message);
            $detail = trim(($code[1] ?? '') . ': ' . ($message[1] ?? ''), ': ');

            throw new StorageException("S3 respondió {$status} al {$action} en el bucket {$this->bucket}" . ($detail !== '' ? " ({$detail})" : '') . '.');
        }

        return [$status, $content, $responseHeaders];
    }

    private function uri(string $path): string
    {
        return ($this->pathStyle ? '/' . rawurlencode($this->bucket) : '') . '/' . self::encode($path);
    }

    private static function encode(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
