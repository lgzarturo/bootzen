<?php

/**
 * Image.php
 *
 * Procesamiento básico de imágenes con la extensión GD: reducir a un tamaño máximo, recortar para
 * llenar un tamaño (avatares, miniaturas) y volver a codificar. Al recodificar se quitan los metadatos
 * EXIF (incluida la ubicación GPS de las fotos del celular) después de aplicar su orientación.
 *
 * @package    BootZen\Support
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Support;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Formatos: JPEG, PNG (con transparencia), WebP y GIF (primer cuadro). Nunca agranda una imagen.
 * Rechaza imágenes de más de 40 megapíxeles antes de decodificarlas, para no agotar la memoria.
 *
 * @example $jpeg = Image::fromString($contenido)->resize(1600)->encode();
 */
final class Image
{
    public const MAX_PIXELS = 40_000_000;

    private const TYPES = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

    private function __construct(private GdImage $image, private readonly string $type)
    {
    }

    /**
     * Abre una imagen desde su contenido.
     *
     * @throws InvalidArgumentException Si no es una imagen soportada o es demasiado grande
     * @throws RuntimeException Si falta la extensión GD
     */
    public static function fromString(string $contents): self
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('El procesamiento de imágenes necesita la extensión gd de PHP.');
        }

        $info = Quietly::run(static fn (): array|false => getimagesizefromstring($contents))[0];
        $type = is_array($info) ? (self::TYPES[$info[2]] ?? null) : null;

        if (! is_array($info) || $type === null) {
            throw new InvalidArgumentException('El archivo no es una imagen JPEG, PNG, WebP o GIF.');
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException("La imagen es demasiado grande ({$info[0]}x{$info[1]} píxeles).");
        }

        $image = Quietly::run(static fn (): GdImage|false => imagecreatefromstring($contents))[0];

        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException('No se pudo leer la imagen: puede estar dañada.');
        }

        return (new self($image, $type))->orient($contents);
    }

    /**
     * Ancho en píxeles.
     *
     * @return int<1, max>
     */
    public function width(): int
    {
        return max(1, imagesx($this->image));
    }

    /**
     * Alto en píxeles.
     *
     * @return int<1, max>
     */
    public function height(): int
    {
        return max(1, imagesy($this->image));
    }

    /**
     * Formato original: jpeg, png, webp o gif.
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Reduce la imagen para que quepa en $maxWidth x $maxHeight conservando la proporción. Si ya es
     * más chica, la deja igual.
     */
    public function resize(int $maxWidth, ?int $maxHeight = null): self
    {
        $maxHeight ??= PHP_INT_MAX;
        $scale = min(1, $maxWidth / $this->width(), $maxHeight / $this->height());

        if ($scale >= 1) {
            return $this;
        }

        return $this->resample(max(1, (int) round($this->width() * $scale)), max(1, (int) round($this->height() * $scale)), 0, 0, $this->width(), $this->height());
    }

    /**
     * Recorta al centro y escala para llenar exactamente $width x $height (avatares, miniaturas). No
     * agranda: si la imagen es más chica, el resultado sale proporcionalmente más chico.
     */
    public function cover(int $width, int $height): self
    {
        $ratio = $width / $height;
        [$cropWidth, $cropHeight] = $this->width() / $this->height() > $ratio
            ? [(int) round($this->height() * $ratio), $this->height()]
            : [$this->width(), (int) round($this->width() / $ratio)];
        $x = intdiv($this->width() - $cropWidth, 2);
        $y = intdiv($this->height() - $cropHeight, 2);
        $scale = min(1, $width / $cropWidth);

        return $this->resample(max(1, (int) round($cropWidth * $scale)), max(1, (int) round($cropHeight * $scale)), $x, $y, $cropWidth, $cropHeight);
    }

    /**
     * Codifica en el formato indicado (por defecto el original). $quality de 0 a 100 para JPEG y WebP.
     *
     * @throws InvalidArgumentException Si el formato no es jpeg, png, webp o gif
     */
    public function encode(?string $type = null, int $quality = 85): string
    {
        $type ??= $this->type;
        $quality = max(0, min(100, $quality));

        if (! in_array($type, ['jpeg', 'jpg', 'png', 'webp', 'gif'], true)) {
            throw new InvalidArgumentException("Formato de imagen [{$type}] no soportado: usa jpeg, png, webp o gif.");
        }

        ob_start();

        try {
            $ok = match ($type) {
                'jpeg', 'jpg' => imagejpeg($this->flattened(), null, $quality),
                'png' => imagepng($this->image, null, 6),
                'webp' => imagewebp($this->image, null, $quality),
                default => imagegif($this->image),
            };
        } finally {
            $data = (string) ob_get_clean();
        }

        if (! $ok || $data === '') {
            throw new RuntimeException("No se pudo codificar la imagen como {$type}.");
        }

        return $data;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function resample(int $width, int $height, int $x, int $y, int $sourceWidth, int $sourceHeight): self
    {
        $target = imagecreatetruecolor($width, $height);

        if (! $target instanceof GdImage) {
            throw new RuntimeException('No se pudo crear la imagen.');
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $this->image, 0, 0, $x, $y, $width, $height, $sourceWidth, $sourceHeight);

        return new self($target, $this->type);
    }

    /**
     * JPEG no tiene transparencia: el fondo transparente queda blanco en lugar de negro.
     */
    private function flattened(): GdImage
    {
        if ($this->type === 'jpeg') {
            return $this->image;
        }

        $flat = imagecreatetruecolor($this->width(), $this->height());

        if (! $flat instanceof GdImage) {
            return $this->image;
        }

        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $this->image, 0, 0, 0, 0, $this->width(), $this->height());

        return $flat;
    }

    /**
     * Gira la foto según la orientación EXIF de la cámara (si hay extensión exif).
     */
    private function orient(string $contents): self
    {
        if ($this->type !== 'jpeg' || ! function_exists('exif_read_data')) {
            return $this;
        }

        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            return $this;
        }

        fwrite($stream, $contents);
        rewind($stream);
        $exif = Quietly::run(static fn (): array|false => exif_read_data($stream))[0];
        fclose($stream);

        $orientation = is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
        $image = $this->image;

        // Primero el espejo, después el giro (imagerotate gira en sentido contrario a las agujas del reloj)
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => 270,
            5, 8 => 90,
            default => 0,
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            $image = $rotated instanceof GdImage ? $rotated : $image;
        }

        return new self($image, $this->type);
    }
}
