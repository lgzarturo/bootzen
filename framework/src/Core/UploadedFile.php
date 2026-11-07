<?php

/**
 * UploadedFile.php
 *
 * Archivo subido en un formulario: tipo detectado por contenido, extensión segura y guardado con un
 * nombre aleatorio en storage/app (privado) o en public/uploads (accesible por URL).
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use BootZen\Support\Image;
use InvalidArgumentException;
use RuntimeException;

/**
 * Se obtiene con $request->file('campo'). Valida antes con las reglas file, image, mimes y max.
 *
 * @example $ruta = $request->file('foto')?->storePublic('avatares'); // 'uploads/avatares/3f9c...jpg'
 * @example $ruta = $request->file('contrato')?->store('contratos');  // 'contratos/a81b...pdf' en storage/app
 */
final class UploadedFile
{
    /**
     * Tipo MIME detectado => extensión con la que se guarda.
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/json' => 'json',
        'application/zip' => 'zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    /**
     * Extensiones que nunca se usan: el servidor podría ejecutarlas.
     */
    private const EXECUTABLE = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phps', 'cgi', 'pl', 'py', 'sh', 'htaccess'];

    /**
     * Extensiones que no van a public/: el navegador ejecutaría su contenido en tu dominio (XSS).
     */
    private const NOT_PUBLIC = ['html', 'htm', 'xhtml', 'svg', 'xml', 'js', 'mjs'];

    /**
     * Archivo generado por resize() o cover(): no llegó por un formulario, pero es de confianza.
     */
    private bool $processed = false;

    /**
     * @param array<string, mixed> $file Entrada de $_FILES (name, type, tmp_name, error, size)
     */
    public function __construct(private readonly array $file)
    {
    }

    /**
     * Crea el archivo desde una entrada de $_FILES, o null si el campo llegó sin archivo.
     *
     * @param array<string, mixed>|null $file
     */
    public static function fromArray(?array $file): ?self
    {
        if ($file === null || ! array_key_exists('tmp_name', $file) || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return new self($file);
    }

    /**
     * Indica si el archivo llegó completo y sin errores.
     */
    public function isValid(): bool
    {
        return $this->error() === UPLOAD_ERR_OK && is_file($this->path());
    }

    public function error(): int
    {
        $error = $this->file['error'] ?? UPLOAD_ERR_NO_FILE;

        return is_int($error) ? $error : UPLOAD_ERR_NO_FILE;
    }

    /**
     * Ruta temporal del archivo.
     */
    public function path(): string
    {
        $path = $this->file['tmp_name'] ?? '';

        return is_string($path) ? $path : '';
    }

    /**
     * Nombre original que envió el navegador (solo para mostrar; no lo uses como ruta).
     */
    public function clientName(): string
    {
        $name = $this->file['name'] ?? '';

        return is_string($name) ? basename(str_replace('\\', '/', $name)) : '';
    }

    /**
     * Tamaño en bytes.
     */
    public function size(): int
    {
        $size = $this->file['size'] ?? 0;

        return is_int($size) ? $size : (is_file($this->path()) ? (int) filesize($this->path()) : 0);
    }

    /**
     * Tipo MIME detectado por el contenido (no el que dice el navegador).
     */
    public function mimeType(): string
    {
        $mime = is_file($this->path()) ? (new \finfo(FILEINFO_MIME_TYPE))->file($this->path()) : false;

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    /**
     * Extensión con la que se guarda: la del tipo detectado, o la del nombre si es inofensiva; si no, 'bin'.
     */
    public function extension(): string
    {
        $known = self::EXTENSIONS[$this->mimeType()] ?? null;

        if ($known !== null) {
            return $known;
        }

        $extension = strtolower(pathinfo($this->clientName(), PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1 && ! in_array($extension, self::EXECUTABLE, true)
            ? $extension
            : 'bin';
    }

    /**
     * Guarda el archivo en un disco privado (por defecto STORAGE_DISK: storage/app) y devuelve su ruta
     * en el disco, por ejemplo 'contratos/a81b...pdf'. Para entregarlo: temporary_url($ruta) o
     * storage()->download($ruta).
     *
     * @param string|null $disk Disco de config/storage.php; null usa el privado por defecto
     * @throws RuntimeException Si el archivo no es válido o no se puede guardar
     */
    public function store(string $directory = 'uploads', ?string $disk = null): string
    {
        $relative = self::directory($directory) . '/' . $this->randomName();
        $contents = $this->contents();
        storage($disk)->put($relative, $contents, $this->mimeType());

        return $relative;
    }

    /**
     * Guarda el archivo en el disco público (por defecto STORAGE_PUBLIC_DISK: public/) bajo uploads/ y
     * devuelve su ruta, por ejemplo 'uploads/avatares/3f9c...jpg'. Se muestra con file_url($ruta).
     * Solo para imágenes, PDFs y archivos que cualquiera puede ver.
     *
     * @param string|null $disk Disco público de config/storage.php; null usa el público por defecto
     * @throws InvalidArgumentException Si el tipo podría ejecutar código en el navegador (html, svg, js) o el disco no es público
     * @throws RuntimeException Si el archivo no es válido o no se puede guardar
     */
    public function storePublic(string $directory = '', ?string $disk = null): string
    {
        $extension = $this->extension();

        if (in_array($extension, self::NOT_PUBLIC, true)) {
            throw new InvalidArgumentException(
                "Los archivos .{$extension} no se guardan en un disco público: el navegador los ejecutaría en tu dominio. Usa store()."
            );
        }

        $contents = $this->contents();
        $target = $disk === null ? storage(self::publicDiskName()) : storage($disk);

        if (! $target->isPublic()) {
            throw new InvalidArgumentException("El disco {$target->name} es privado: storePublic() necesita un disco con 'visibility' => 'public'. Usa store().");
        }

        $relative = rtrim('uploads/' . self::directory($directory, true), '/') . '/' . $this->randomName();
        $target->put($relative, $contents, $this->mimeType());

        return $relative;
    }

    private function randomName(): string
    {
        return bin2hex(random_bytes(16)) . '.' . $this->extension();
    }

    /**
     * Copia de la imagen reducida para caber en $maxWidth x $maxHeight (nunca la agranda), sin metadatos
     * EXIF y con la orientación de la cámara aplicada. Se guarda igual: ->resize(1600)->storePublic('productos').
     *
     * @throws InvalidArgumentException Si no es una imagen JPEG, PNG, WebP o GIF, o es demasiado grande
     */
    public function resize(int $maxWidth, ?int $maxHeight = null, int $quality = 85): self
    {
        return $this->processedCopy(Image::fromString($this->contents())->resize($maxWidth, $maxHeight)->encode(null, $quality));
    }

    /**
     * Copia de la imagen recortada al centro para llenar $width x $height (avatares, miniaturas), sin
     * metadatos EXIF. Ejemplo: ->cover(300, 300)->storePublic('avatares').
     *
     * @throws InvalidArgumentException Si no es una imagen JPEG, PNG, WebP o GIF, o es demasiado grande
     */
    public function cover(int $width, int $height, int $quality = 85): self
    {
        return $this->processedCopy(Image::fromString($this->contents())->cover($width, $height)->encode(null, $quality));
    }

    /**
     * Contenido del archivo subido.
     *
     * @throws RuntimeException Si la subida falló o el archivo no llegó por un formulario
     */
    public function contents(): string
    {
        if (! $this->isValid()) {
            throw new RuntimeException('El archivo no se subió bien (código ' . $this->error() . '). Valida el campo con la regla file.');
        }

        // En una petición real solo se leen archivos que PHP recibió; en CLI (tests y comandos) cualquiera
        $readable = ($this->processed || is_uploaded_file($this->path()) || PHP_SAPI === 'cli') && is_readable($this->path());
        $contents = $readable ? file_get_contents($this->path()) : false;

        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }

        return $contents;
    }

    private function processedCopy(string $contents): self
    {
        $path = tempnam(sys_get_temp_dir(), 'bootzen-img');

        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('No se pudo guardar la imagen procesada en la carpeta temporal.');
        }

        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        $copy = new self(['name' => $this->clientName(), 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contents)]);
        $copy->processed = true;

        return $copy;
    }

    private static function publicDiskName(): string
    {
        $name = config('storage.public', 'public');

        return is_string($name) && $name !== '' ? $name : 'public';
    }

    /**
     * Normaliza el directorio: sin '..', sin barras al inicio ni al final.
     *
     * @throws InvalidArgumentException Si el directorio sale de la carpeta base
     */
    private static function directory(string $directory, bool $allowEmpty = false): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');

        if (($directory === '' && ! $allowEmpty) || preg_match('#(^|/)\.\.(/|$)#', $directory) === 1 || preg_match('#^[A-Za-z0-9_/-]*$#', $directory) !== 1) {
            throw new InvalidArgumentException("Directorio inválido [{$directory}]: usa letras, números, guiones y barras.");
        }

        return $directory;
    }
}
