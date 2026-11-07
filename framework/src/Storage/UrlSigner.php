<?php

/**
 * UrlSigner.php
 *
 * Firma y verifica las URLs temporales de /_bootzen/archivo con la APP_KEY (HMAC-SHA256): sirven
 * para entregar archivos privados de discos sin URLs propias (local, FTP, SFTP).
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

use SensitiveParameter;

/**
 * @internal Lo usan Storage y FileController.
 */
final class UrlSigner
{
    public const PATH = '/_bootzen/archivo';

    /**
     * @param string $base Prefijo de las URLs (la ruta de APP_URL, por ejemplo '' o '/tienda')
     */
    public function __construct(#[SensitiveParameter] private readonly string $key, private readonly string $base = '')
    {
    }

    public function url(string $disk, string $path, int $expires, ?string $name = null): string
    {
        $query = array_filter(['d' => $disk, 'p' => $path, 'e' => (string) $expires, 'n' => $name], static fn (?string $value): bool => $value !== null);
        $query['s'] = $this->signature($disk, $path, $expires, $name);

        return rtrim($this->base, '/') . self::PATH . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Valida firma y vencimiento. Devuelve [disco, ruta, nombre] o null si no es válida.
     *
     * @param array<string, mixed> $query
     * @return array{string, string, string|null}|null
     */
    public function verify(array $query, int $now): ?array
    {
        $disk = $query['d'] ?? null;
        $path = $query['p'] ?? null;
        $expires = $query['e'] ?? null;
        $name = $query['n'] ?? null;
        $signature = $query['s'] ?? null;

        if (! is_string($disk) || ! is_string($path) || ! is_string($expires) || ! ctype_digit($expires) || ! is_string($signature) || ($name !== null && ! is_string($name))) {
            return null;
        }

        if ((int) $expires < $now || ! hash_equals($this->signature($disk, $path, (int) $expires, $name), $signature)) {
            return null;
        }

        return [$disk, $path, $name];
    }

    private function signature(string $disk, string $path, int $expires, ?string $name): string
    {
        return hash_hmac('sha256', implode("\n", [$disk, $path, $expires, $name ?? '']), 'bootzen-archivo|' . $this->key);
    }
}
