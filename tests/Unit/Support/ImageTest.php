<?php

declare(strict_types=1);

use BootZen\Support\Image;

/**
 * Imagen de prueba: mitad izquierda roja y mitad derecha azul.
 */
function testImage(int $width, int $height, string $type = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, (int) imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 255));
    ob_start();
    $type === 'png' ? imagepng($image) : imagejpeg($image, null, 95);

    return (string) ob_get_clean();
}

/**
 * Inserta un bloque EXIF con la orientación indicada (y una descripción) justo después del inicio del JPEG.
 */
function withExif(string $jpeg, int $orientation): string
{
    $tiff = "MM\x00\x2A\x00\x00\x00\x08"
        . "\x00\x02"
        . "\x01\x0E\x00\x02\x00\x00\x00\x04GPS\x00"
        . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00"
        . "\x00\x00\x00\x00";
    $app1 = "Exif\x00\x00" . $tiff;

    return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
}

/**
 * Color dominante de un píxel: 'rojo' o 'azul'.
 */
function colorAt(string $contents, int $x, int $y): string
{
    $image = imagecreatefromstring($contents);
    $rgb = imagecolorat($image, $x, $y);

    return (($rgb >> 16) & 0xFF) > ($rgb & 0xFF) ? 'rojo' : 'azul';
}

it('reduce sin deformar y nunca agranda', function (): void {
    $image = Image::fromString(testImage(4000, 3000));
    $small = $image->resize(1600);

    expect([$small->width(), $small->height()])->toBe([1600, 1200])
        ->and([$image->resize(1000, 500)->width(), $image->resize(1000, 500)->height()])->toBe([667, 500])
        ->and($image->resize(8000)->width())->toBe(4000)
        ->and($small->type())->toBe('jpeg');
});

it('recorta al centro para llenar un tamaño', function (): void {
    $wide = Image::fromString(testImage(400, 200))->cover(100, 100);
    $tall = Image::fromString(testImage(200, 600))->cover(300, 100);
    $tiny = Image::fromString(testImage(50, 50))->cover(200, 200);

    expect([$wide->width(), $wide->height()])->toBe([100, 100])
        ->and(colorAt($wide->encode(), 10, 50))->toBe('rojo')
        ->and(colorAt($wide->encode(), 90, 50))->toBe('azul')
        ->and([$tall->width(), $tall->height()])->toBe([200, 67])
        ->and([$tiny->width(), $tiny->height()])->toBe([50, 50]);
});

it('aplica la orientación de la cámara y quita los metadatos', function (int $orientation, int $width, int $height, string $top): void {
    $photo = withExif(testImage(40, 20), $orientation);

    expect(exif_read_data('data://image/jpeg;base64,' . base64_encode($photo))['Orientation'] ?? null)->toBe($orientation);

    $fixed = Image::fromString($photo)->encode();

    expect([imagesx(imagecreatefromstring($fixed)), imagesy(imagecreatefromstring($fixed))])->toBe([$width, $height])
        ->and(colorAt($fixed, 2, 2))->toBe($top)
        ->and(str_contains($fixed, 'Exif'))->toBeFalse();
})->with([
    // Esquina superior izquierda esperada al corregir (la foto guardada tiene rojo a la izquierda)
    'normal' => [1, 40, 20, 'rojo'],
    'espejo' => [2, 40, 20, 'azul'],
    'girada 180' => [3, 40, 20, 'azul'],
    'girada 180 con espejo' => [4, 40, 20, 'rojo'],
    'espejo y 90 a la izquierda' => [5, 20, 40, 'rojo'],
    'girada 90 a la derecha' => [6, 20, 40, 'rojo'],
    'espejo y 90 a la derecha' => [7, 20, 40, 'azul'],
    'girada 90 a la izquierda' => [8, 20, 40, 'azul'],
]);

it('convierte de formato y conserva la transparencia en PNG', function (): void {
    $png = imagecreatetruecolor(10, 10);
    imagesavealpha($png, true);
    imagefill($png, 0, 0, (int) imagecolorallocatealpha($png, 0, 0, 0, 127));
    ob_start();
    imagepng($png);
    $image = Image::fromString((string) ob_get_clean());

    $again = imagecreatefromstring($image->resize(5)->encode());
    $jpeg = $image->encode('jpeg');

    expect((imagecolorat($again, 2, 2) >> 24) & 0x7F)->toBe(127)
        ->and(getimagesizefromstring($jpeg)[2])->toBe(IMAGETYPE_JPEG)
        ->and(imagecolorat(imagecreatefromstring($jpeg), 2, 2) & 0xFFFFFF)->toBe(0xFFFFFF)
        ->and(getimagesizefromstring(Image::fromString(testImage(10, 10))->encode('webp'))[2])->toBe(IMAGETYPE_WEBP)
        ->and(fn () => $image->encode('bmp'))->toThrow(InvalidArgumentException::class, 'no soportado');
});

it('rechaza lo que no es imagen y las imágenes gigantes', function (): void {
    $gif = "GIF89a" . pack('vv', 20000, 20000) . "\x00\x00\x00";

    expect(fn () => Image::fromString('%PDF-1.4'))->toThrow(InvalidArgumentException::class, 'no es una imagen JPEG, PNG, WebP o GIF')
        ->and(fn () => Image::fromString($gif))->toThrow(InvalidArgumentException::class, 'demasiado grande (20000x20000 píxeles)');
});
