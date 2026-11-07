<?php

/**
 * Storage.php
 *
 * Un disco configurado en config/storage.php, listo para usar: storage() (privado por defecto),
 * storage('s3') o el disco público. Guarda, lee, borra, da la URL pública y URLs temporales para
 * archivos privados, y los entrega como descarga.
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

use BootZen\Core\Response;
use finfo;

/**
 * Las rutas no pueden salir del disco ('..'). En un disco público no se guardan archivos que el
 * navegador o el servidor ejecutarían (php, html, svg, js, .htaccess).
 *
 * @example storage()->put('reportes/marzo.csv', $csv); $url = storage()->temporaryUrl('reportes/marzo.csv', 10);
 */
final class Storage
{
    public const EXECUTABLE = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps', 'cgi', 'pl', 'py', 'sh',
        'htaccess', 'htpasswd', 'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'js', 'mjs', 'xml', 'xsl',
    ];

    public function __construct(
        public readonly string $name,
        private readonly Disk $disk,
        private readonly bool $public = false,
        private readonly string $prefix = '',
        private readonly ?UrlSigner $signer = null
    ) {
    }

    /**
     * Guarda el contenido en la ruta. El tipo se detecta del contenido si no se indica.
     *
     * @throws StorageException Si la ruta no es válida, el tipo no se permite en un disco público o el disco falla
     */
    public function put(string $path, string $contents, ?string $mime = null): void
    {
        $path = $this->normalize($path);

        if ($this->public && (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXECUTABLE, true) || str_starts_with(basename($path), '.'))) {
            throw new StorageException("No se guardan archivos .{$this->extension($path)} en el disco público {$this->name}: el navegador o el servidor los ejecutarían. Usa un disco privado.");
        }

        $this->disk->put($this->full($path), $contents, $mime ?? self::detectMime($contents, $path));
    }

    /**
     * Copia un archivo del servidor al disco.
     *
     * @throws StorageException
     */
    public function putFile(string $path, string $localFile, ?string $mime = null): void
    {
        $contents = is_file($localFile) && is_readable($localFile) ? file_get_contents($localFile) : false;

        if ($contents === false) {
            throw new StorageException("No se puede leer {$localFile}.");
        }

        $this->put($path, $contents, $mime);
    }

    /**
     * Contenido completo del archivo.
     *
     * @throws StorageException Si no existe
     */
    public function get(string $path): string
    {
        return $this->disk->get($this->full($this->normalize($path)));
    }

    /**
     * Indica si el archivo existe en el disco.
     */
    public function exists(string $path): bool
    {
        return $this->disk->exists($this->full($this->normalize($path)));
    }

    /**
     * Borra el archivo. Devuelve false si no existía.
     */
    public function delete(string $path): bool
    {
        return $this->disk->delete($this->full($this->normalize($path)));
    }

    /**
     * Tamaño en bytes.
     *
     * @throws StorageException Si no existe
     */
    public function size(string $path): int
    {
        return $this->disk->size($this->full($this->normalize($path)));
    }

    /**
     * URL pública del archivo (disco público).
     *
     * @throws StorageException Si el disco es privado: usa temporaryUrl()
     */
    public function url(string $path): string
    {
        $url = $this->public ? $this->disk->url($this->full($this->normalize($path))) : null;

        if ($url === null) {
            throw new StorageException("El disco {$this->name} es privado o no tiene URL: usa temporaryUrl() o download().");
        }

        return $url;
    }

    /**
     * URL que permite descargar el archivo durante $minutes minutos, sin sesión. Con $downloadName
     * se descarga con ese nombre.
     *
     * @throws StorageException Si no hay APP_KEY para firmarla
     */
    public function temporaryUrl(string $path, int $minutes = 30, ?string $downloadName = null): string
    {
        $path = $this->normalize($path);
        $expires = time() + max(1, $minutes) * 60;

        if ($this->disk instanceof TemporaryUrls) {
            return $this->disk->temporaryUrl($this->full($path), $expires, $downloadName);
        }

        if ($this->signer === null) {
            throw new StorageException('Define APP_KEY en .env para generar URLs temporales.');
        }

        return $this->signer->url($this->name, $path, $expires, $downloadName);
    }

    /**
     * Respuesta que entrega el archivo: para descargar (por defecto) o para verlo en el navegador
     * con $inline (solo imágenes, PDF y texto; lo demás siempre se descarga).
     *
     * @throws StorageException Si no existe
     */
    public function download(string $path, ?string $name = null, bool $inline = false): Response
    {
        $path = $this->normalize($path);
        $contents = $this->get($path);
        $mime = self::detectMime($contents, $path);
        $safe = preg_match('#^(image/(jpeg|png|gif|webp|avif)|application/pdf|text/plain)$#', $mime) === 1;
        $filename = $name ?? basename($path);
        $ascii = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);

        return new Response(200, $contents, [
            'content-type' => $mime,
            'content-length' => (string) strlen($contents),
            'content-disposition' => ($inline && $safe ? 'inline' : 'attachment') . "; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($filename),
            // Si el archivo es HTML o similar, el navegador no puede ejecutarlo en tu dominio
            'content-security-policy' => 'sandbox',
            'x-content-type-options' => 'nosniff',
            'cache-control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * El driver por debajo (LocalDisk, S3Disk, FtpDisk o SftpDisk).
     */
    public function disk(): Disk
    {
        return $this->disk;
    }

    /**
     * Tipo MIME por contenido (finfo) o, si no se puede, por extensión.
     */
    public static function detectMime(string $contents, string $path = ''): string
    {
        $detected = class_exists(finfo::class) ? (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) : false;

        if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream' && ! ($detected === 'text/plain' && str_ends_with($path, '.csv'))) {
            return $detected;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            default => is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream',
        };
    }

    /**
     * @throws StorageException
     */
    private function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '' || preg_match('#(^|/)\.{1,2}(/|$)|//|[\x00-\x1F\x7F]#', $path) === 1) {
            throw new StorageException("Ruta inválida [{$path}]: sin '..', sin '//' y sin caracteres de control.");
        }

        return $path;
    }

    private function full(string $path): string
    {
        return $this->prefix === '' ? $path : trim($this->prefix, '/') . '/' . $path;
    }

    private function extension(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : basename($path);
    }
}
