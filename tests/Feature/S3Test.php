<?php

declare(strict_types=1);

/**
 * Integración con un servidor S3 compatible real. Se ejecuta solo si existe BOOTZEN_TEST_S3_ENDPOINT
 * (CI la define). BOOTZEN_TEST_S3_ENDPOINT, _KEY y _SECRET. El servidor debe dejar leer sin
 * credenciales el bucket "publico" (para probar las URLs públicas).
 */

use BootZen\Storage\S3Disk;
use BootZen\Storage\S3Signer;
use BootZen\Storage\StorageException;
use BootZen\Storage\StorageManager;

function s3Env(string $key, string $default = ''): string
{
    $value = getenv('BOOTZEN_TEST_S3_' . $key);

    return $value === false ? $default : $value;
}

/**
 * Crea el bucket si no existe (PUT /bucket firmado, estilo path).
 */
function s3Bucket(string $bucket): void
{
    $parts = parse_url(s3Env('ENDPOINT'));
    $host = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $headers = (new S3Signer(s3Env('KEY'), s3Env('SECRET'), 'us-east-1'))
        ->sign('PUT', '/' . $bucket, [], ['host' => $host], hash('sha256', ''), new DateTimeImmutable());
    unset($headers['host']);

    file_get_contents(s3Env('ENDPOINT') . '/' . $bucket, false, stream_context_create(['http' => [
        'method' => 'PUT',
        'header' => implode("\r\n", array_map(static fn ($k, $v) => "{$k}: {$v}", array_keys($headers), $headers)),
        'ignore_errors' => true,
    ]]));
}

/**
 * Descarga una URL sin credenciales y devuelve [código, cuerpo, cabeceras].
 *
 * @return array{int, string, list<string>}
 */
function s3Fetch(string $url): array
{
    $body = (string) file_get_contents($url, false, stream_context_create(['http' => ['ignore_errors' => true]]));
    $headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : $http_response_header;
    preg_match('#HTTP/\S+\s+(\d{3})#', $headers[0] ?? '', $match);

    return [(int) ($match[1] ?? 0), $body, $headers];
}

beforeEach(function (): void {
    if (s3Env('ENDPOINT') === '') {
        $this->markTestSkipped('S3 no configurado (BOOTZEN_TEST_S3_ENDPOINT).');
    }

    s3Bucket('privado');
    s3Bucket('publico');

    $this->manager = new StorageManager(['disks' => [
        's3' => ['driver' => 's3', 'key' => s3Env('KEY'), 'secret' => s3Env('SECRET'), 'bucket' => 'privado', 'endpoint' => s3Env('ENDPOINT'), 'path_style' => true, 'prefix' => 'app'],
        's3-public' => ['driver' => 's3', 'key' => s3Env('KEY'), 'secret' => s3Env('SECRET'), 'bucket' => 'publico', 'endpoint' => s3Env('ENDPOINT'), 'path_style' => true, 'visibility' => 'public'],
    ]], sys_get_temp_dir());
});

it('guarda, lee, mide y borra con nombres en UTF-8 y espacios', function (): void {
    $disk = $this->manager->disk('s3');
    $content = '%PDF-1.4 ' . random_bytes(5000);
    $disk->put('facturas/2026/Factura ñ #12.pdf', $content);

    expect($disk->exists('facturas/2026/Factura ñ #12.pdf'))->toBeTrue()
        ->and($disk->get('facturas/2026/Factura ñ #12.pdf'))->toBe($content)
        ->and($disk->size('facturas/2026/Factura ñ #12.pdf'))->toBe(strlen($content))
        ->and($disk->delete('facturas/2026/Factura ñ #12.pdf'))->toBeTrue()
        ->and($disk->delete('facturas/2026/Factura ñ #12.pdf'))->toBeFalse()
        ->and($disk->exists('facturas/2026/Factura ñ #12.pdf'))->toBeFalse()
        ->and(fn () => $disk->get('nada.pdf'))->toThrow(StorageException::class, 'No existe el archivo');
});

it('entrega privados con una URL prefirmada que vence y públicos con su URL', function (): void {
    $private = $this->manager->disk('s3');
    $private->put('contratos/c 1.pdf', '%PDF contrato');
    [$status, $body] = s3Fetch($private->temporaryUrl('contratos/c 1.pdf', 5));
    // El nombre de descarga viaja firmado (response-content-disposition); AWS y R2 lo aplican, otros lo ignoran
    [$withName] = s3Fetch($private->temporaryUrl('contratos/c 1.pdf', 5, 'Contrato Ana.pdf'));

    expect($status)->toBe(200)
        ->and($body)->toBe('%PDF contrato')
        ->and($withName)->toBe(200)
        ->and(s3Fetch(str_replace('X-Amz-Signature=', 'X-Amz-Signature=0', $private->temporaryUrl('contratos/c 1.pdf', 5)))[0])->toBe(403);

    $public = $this->manager->disk('s3-public');
    $public->put('uploads/fotos/a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg=='));
    [$publicStatus, , $publicHeaders] = s3Fetch($public->url('uploads/fotos/a.png'));

    expect($publicStatus)->toBe(200)
        ->and(implode("\n", $publicHeaders))->toContain('image/png')
        ->and(fn () => $private->url('contratos/c 1.pdf'))->toThrow(StorageException::class, 'es privado');
});

it('explica las credenciales malas y el servidor caído', function (): void {
    $bad = new S3Disk('privado', 'us-east-1', s3Env('KEY'), 'otra-clave', s3Env('ENDPOINT'), true);

    expect(fn () => $bad->put('a.txt', 'x'))->toThrow(StorageException::class, 'S3 respondió 403 al subir a.txt en el bucket privado')
        ->and(fn () => (new S3Disk('b', 'us-east-1', 'k', 's', 'http://127.0.0.1:1', true))->put('a.txt', 'x'))
        ->toThrow(StorageException::class, 'No se pudo conectar con 127.0.0.1:1');
});
