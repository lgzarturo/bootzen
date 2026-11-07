<?php

declare(strict_types=1);

use BootZen\Core\HttpException;
use BootZen\Core\Request;
use BootZen\Storage\FileController;
use BootZen\Storage\LocalDisk;
use BootZen\Storage\Storage;
use BootZen\Storage\StorageException;
use BootZen\Storage\StorageManager;
use BootZen\Storage\UrlSigner;

beforeEach(function (): void {
    $this->dir = makeTempDir('bootzen-storage');
    $this->signer = new UrlSigner('base64:clave-de-prueba', '/tienda');
    $this->manager = new StorageManager([
        'default' => 'privado',
        'public' => 'publico',
        'disks' => [
            'privado' => ['driver' => 'local', 'root' => 'privado', 'visibility' => 'private'],
            'publico' => ['driver' => 'local', 'root' => 'web', 'visibility' => 'public', 'url' => 'https://cdn.tienda.mx'],
            'facturas' => ['driver' => 'local', 'root' => 'privado', 'visibility' => 'private', 'prefix' => 'facturas/2026'],
        ],
    ], $this->dir, $this->signer, '/tienda');
});

afterEach(fn () => removeDir($this->dir));

it('guarda, lee, mide y borra en un disco local sin dejar temporales', function (): void {
    $disk = $this->manager->disk();
    $disk->put('reportes/marzo.csv', "id,total\n1,10");

    expect($disk->exists('reportes/marzo.csv'))->toBeTrue()
        ->and($disk->get('reportes/marzo.csv'))->toBe("id,total\n1,10")
        ->and($disk->size('reportes/marzo.csv'))->toBe(13)
        ->and(glob($this->dir . '/privado/reportes/.*.tmp'))->toBe([])
        ->and($disk->delete('reportes/marzo.csv'))->toBeTrue()
        ->and($disk->delete('reportes/marzo.csv'))->toBeFalse()
        ->and($disk->exists('reportes/marzo.csv'))->toBeFalse()
        ->and(fn () => $disk->get('reportes/marzo.csv'))->toThrow(StorageException::class, 'No existe el archivo reportes/marzo.csv')
        ->and(fn () => $disk->size('nada.txt'))->toThrow(StorageException::class, 'No existe');
});

it('rechaza rutas que salen del disco', function (string $path): void {
    expect(fn () => $this->manager->disk()->put($path, 'x'))->toThrow(StorageException::class, 'Ruta inválida');
})->with(['../fuera.txt', 'a/../../b.txt', './a.txt', 'a//b.txt', '', "a\0.txt", "a\nb.txt"]);

it('no guarda en un disco público lo que el navegador o el servidor ejecutarían', function (string $path): void {
    expect(fn () => $this->manager->publicDisk()->put($path, 'x'))->toThrow(StorageException::class, 'Usa un disco privado');

    // En un disco privado sí se puede (se entrega siempre como descarga)
    $this->manager->disk()->put($path, 'x');
    expect($this->manager->disk()->exists($path))->toBeTrue();
})->with(['shell.php', 'x/pagina.HTML', 'logo.svg', '.htaccess', 'app.js', 'x/.user.ini']);

it('aplica el prefijo del disco', function (): void {
    $this->manager->disk('facturas')->put('f-12.pdf', '%PDF');

    expect($this->dir . '/privado/facturas/2026/f-12.pdf')->toBeFile()
        ->and($this->manager->disk('facturas')->get('f-12.pdf'))->toBe('%PDF');
});

it('da la URL pública solo en discos públicos', function (): void {
    expect($this->manager->publicDisk()->url('uploads/ñandú 1.jpg'))->toBe('https://cdn.tienda.mx/uploads/%C3%B1and%C3%BA%201.jpg')
        ->and(fn () => $this->manager->disk()->url('a.pdf'))->toThrow(StorageException::class, 'es privado o no tiene URL: usa temporaryUrl()');

    $sinUrl = new StorageManager([], $this->dir, null, '/tienda');
    expect($sinUrl->publicDisk()->url('uploads/a.jpg'))->toBe('/tienda/uploads/a.jpg');
});

it('entrega archivos como descarga y solo muestra en el navegador los tipos seguros', function (): void {
    $disk = $this->manager->disk();
    $disk->put('fotos/a.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg=='));
    $disk->put('raro/pagina.html', '<script>alert(1)</script>');

    $image = $disk->download('fotos/a.png', 'Foto ñ.png', inline: true);
    $html = $disk->download('raro/pagina.html', inline: true);

    expect($image->getHeader('content-type'))->toBe('image/png')
        ->and($image->getHeader('content-disposition'))->toBe("inline; filename=\"Foto_.png\"; filename*=UTF-8''Foto%20%C3%B1.png")
        ->and($image->getHeader('content-security-policy'))->toBe('sandbox')
        ->and($html->getHeader('content-disposition'))->toStartWith('attachment; filename="pagina.html"')
        ->and($html->getHeader('x-content-type-options'))->toBe('nosniff');
});

it('firma URLs temporales y el controlador las verifica', function (): void {
    $this->manager->disk()->put('contratos/c-1.pdf', '%PDF-1.4 contrato');
    $url = $this->manager->disk()->temporaryUrl('contratos/c-1.pdf', 5, 'Contrato.pdf');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $controller = new FileController($this->manager, $this->signer);
    $request = fn (array $query): Request => new Request('GET', '/tienda/_bootzen/archivo?' . http_build_query($query));

    expect($url)->toStartWith('/tienda/_bootzen/archivo?d=privado&p=contratos%2Fc-1.pdf&e=')
        ->and($controller->show($request($query))->getBody())->toBe('%PDF-1.4 contrato')
        ->and($controller->show($request($query))->getHeader('content-disposition'))->toStartWith('attachment; filename="Contrato.pdf"');

    $tampered = ['p' => 'contratos/otro.pdf'] + $query;
    $expired = ['e' => (string) (time() - 1)] + $query;

    expect(fn () => $controller->show($request($tampered)))->toThrow(HttpException::class, 'El enlace no es válido o ya venció.')
        ->and(fn () => $controller->show($request($expired)))->toThrow(HttpException::class, 'ya venció')
        ->and(fn () => $controller->show($request([])))->toThrow(HttpException::class);

    $this->manager->disk()->delete('contratos/c-1.pdf');
    expect(fn () => $controller->show($request($query)))->toThrow(HttpException::class, 'El archivo ya no existe.');
});

it('pide la APP_KEY para URLs temporales y explica los discos mal configurados', function (): void {
    $manager = new StorageManager(['disks' => [
        'raro' => ['driver' => 'dropbox'],
        's3' => ['driver' => 's3', 'bucket' => 'b'],
        'ftp' => ['driver' => 'ftp', 'host' => 'ftp.x.com'],
        'sftp' => ['driver' => 'sftp', 'host' => 'x.com', 'username' => 'u'],
    ]], $this->dir);

    expect(fn () => $manager->disk()->temporaryUrl('a.pdf'))->toThrow(StorageException::class, 'Define APP_KEY')
        ->and($manager->names())->toBe(['raro', 's3', 'ftp', 'sftp', 'local', 'public'])
        ->and(fn () => $manager->disk('nube'))->toThrow(StorageException::class, 'El disco [nube] no está en config/storage.php')
        ->and(fn () => $manager->disk('raro'))->toThrow(StorageException::class, 'Driver [dropbox] desconocido')
        ->and(fn () => $manager->disk('s3'))->toThrow(StorageException::class, 'le falta: key, secret')
        ->and(fn () => $manager->disk('ftp'))->toThrow(StorageException::class, 'le falta: username, password')
        ->and(fn () => $manager->disk('sftp'))->toThrow(StorageException::class, 'le falta: password o private_key');
});

it('detecta el tipo por contenido o por extensión', function (): void {
    expect(Storage::detectMime('%PDF-1.4', 'a.pdf'))->toBe('application/pdf')
        ->and(Storage::detectMime("id,total\n1,2", 'a.csv'))->toBe('text/csv')
        ->and(Storage::detectMime('{"a":1}', 'a.json'))->toBe('application/json')
        ->and(Storage::detectMime(str_repeat("\x01\x02\x03\xFE", 8), 'a.bin'))->toBe('application/octet-stream');
});

it('informa si no puede escribir en la carpeta', function (): void {
    writeFile($this->dir . '/archivo', 'no soy carpeta');

    expect(fn () => (new LocalDisk($this->dir . '/archivo'))->put('a.txt', 'x'))->toThrow(StorageException::class, 'No se pudo crear la carpeta');
});
