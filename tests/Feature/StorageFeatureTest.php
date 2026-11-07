<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\UploadedFile;
use BootZen\Testing\TestClient;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-storage-app');
    writeFile($this->project . '/config/app.php', "<?php return ['key' => 'base64:clave', 'url' => 'https://tienda.mx/app'];");
    writeFile($this->project . '/config/storage.php', "<?php return ['disks' => ['archivo' => ['driver' => 'local', 'root' => 'storage/archivo', 'visibility' => 'private'], 'cdn' => ['driver' => 'local', 'root' => 'storage/cdn', 'visibility' => 'public', 'url' => 'https://cdn.tienda.mx']]];");
    $this->app = new Application($this->project);
});

afterEach(fn () => removeDir($this->project));

it('guarda subidas en los discos por defecto con las mismas rutas de siempre', function (): void {
    $privado = UploadedFile::fromArray(TestClient::file('contrato.pdf', '%PDF-1.4 x'))?->store('contratos');
    $publico = UploadedFile::fromArray(TestClient::image('foto.png'))?->storePublic('clientes');

    expect($privado)->toMatch('#^contratos/[a-f0-9]{32}\.pdf$#')
        ->and($this->project . '/storage/app/' . $privado)->toBeFile()
        ->and($publico)->toMatch('#^uploads/clientes/[a-f0-9]{32}\.png$#')
        ->and($this->project . '/public/' . $publico)->toBeFile()
        ->and(file_url((string) $publico))->toBe('/app/' . $publico)
        ->and(storage()->get((string) $privado))->toBe('%PDF-1.4 x');
});

it('elige otro disco para store() y storePublic()', function (): void {
    $privado = UploadedFile::fromArray(TestClient::file('a.txt', 'hola'))?->store('notas', 'archivo');
    $publico = UploadedFile::fromArray(TestClient::image())?->storePublic('fotos', 'cdn');

    expect($this->project . '/storage/archivo/' . $privado)->toBeFile()
        ->and(file_url((string) $publico, 'cdn'))->toStartWith('https://cdn.tienda.mx/uploads/fotos/')
        ->and(fn () => UploadedFile::fromArray(TestClient::image())?->storePublic('x', 'archivo'))
        ->toThrow(InvalidArgumentException::class, 'El disco archivo es privado: storePublic() necesita un disco con');
});

it('entrega archivos privados por la URL temporal sin sesión', function (): void {
    storage()->put('facturas/f-1.pdf', '%PDF-1.4 factura');
    $url = temporary_url('facturas/f-1.pdf', 5);

    expect($url)->toStartWith('/app/_bootzen/archivo?d=local&p=facturas%2Ff-1.pdf');

    $response = $this->app->run(new Request('GET', substr($url, strlen('/app'))));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody())->toBe('%PDF-1.4 factura')
        ->and($response->getHeader('content-disposition'))->toStartWith('inline;')
        ->and($this->app->run(new Request('GET', '/_bootzen/archivo?d=local&p=facturas%2Ff-1.pdf&e=9999999999&s=falsa'))->getStatusCode())->toBe(403);
});

it('reduce y recorta imágenes subidas antes de guardarlas', function (): void {
    $image = imagecreatetruecolor(3000, 2000);
    ob_start();
    imagejpeg($image);
    $upload = UploadedFile::fromArray(TestClient::file('foto.jpg', (string) ob_get_clean()));

    $grande = $upload?->resize(1200)->storePublic('productos');
    $mini = $upload?->cover(300, 300)->storePublic('productos/mini');

    expect(getimagesize($this->project . '/public/' . $grande))->toMatchArray([0 => 1200, 1 => 800, 2 => IMAGETYPE_JPEG])
        ->and(getimagesize($this->project . '/public/' . $mini))->toMatchArray([0 => 300, 1 => 300])
        ->and(fn () => UploadedFile::fromArray(TestClient::file('a.pdf', '%PDF-1.4'))?->resize(100))
        ->toThrow(InvalidArgumentException::class, 'no es una imagen');
});
