<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\UploadedFile;
use BootZen\Testing\TestClient;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-upload');
    new Application($this->project);
});

afterEach(fn () => removeDir($this->project));

it('devuelve null si el campo no trae archivo', function (): void {
    $request = new Request('POST', '/', [], [], ['foto' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]]);

    expect($request->file('foto'))->toBeNull()
        ->and($request->file('otro'))->toBeNull();
});

it('detecta el tipo por contenido y guarda en storage/app con nombre aleatorio', function (): void {
    $file = (new Request('POST', '/', [], [], ['foto' => TestClient::image('Mi Foto.PNG')]))->file('foto');

    expect($file)->toBeInstanceOf(UploadedFile::class)
        ->and($file?->isValid())->toBeTrue()
        ->and($file?->mimeType())->toBe('image/png')
        ->and($file?->extension())->toBe('png')
        ->and($file?->clientName())->toBe('Mi Foto.PNG')
        ->and($file?->size())->toBeGreaterThan(50);

    $path = (string) $file?->store('fotos/2026');

    expect($path)->toMatch('#^fotos/2026/[a-f0-9]{32}\.png$#')
        ->and($this->project . '/storage/app/' . $path)->toBeFile();
});

it('guarda en public/uploads y devuelve la ruta para asset()', function (): void {
    $path = UploadedFile::fromArray(TestClient::image())?->storePublic('avatares');

    expect($path)->toMatch('#^uploads/avatares/[a-f0-9]{32}\.png$#')
        ->and($this->project . '/public/' . $path)->toBeFile()
        ->and(UploadedFile::fromArray(TestClient::image())?->storePublic())->toMatch('#^uploads/[a-f0-9]{32}\.png$#');
});

it('nunca guarda con una extensión ejecutable', function (string $name, string $contents, string $extension): void {
    expect(UploadedFile::fromArray(TestClient::file($name, $contents))?->extension())->toBe($extension);
})->with([
    'php disfrazado de imagen' => ['foto.php', '<?php echo 1;', 'bin'],
    'phtml con texto plano' => ['x.phtml', 'hola', 'txt'],
    'texto' => ['notas.txt', 'hola', 'txt'],
    'extensión rara pero inofensiva' => ['datos.ics', "BEGIN:VCALENDAR\x00\x01", 'ics'],
    'sin extensión' => ['archivo', "\x00\x01\x02", 'bin'],
]);

it('no guarda en public/ archivos que el navegador ejecutaría', function (): void {
    UploadedFile::fromArray(TestClient::file('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))?->storePublic();
})->throws(InvalidArgumentException::class, 'no se guardan en public/');

it('rechaza directorios que salen de la carpeta', function (string $directory): void {
    expect(fn () => UploadedFile::fromArray(TestClient::image())?->store($directory))
        ->toThrow(InvalidArgumentException::class, 'Directorio inválido');
})->with(['../fuera', 'a/../../b', '', 'con espacio']);

it('falla con un mensaje claro si el archivo no llegó bien', function (): void {
    $file = UploadedFile::fromArray(['name' => 'a.png', 'tmp_name' => '/no/existe', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]);

    expect($file?->isValid())->toBeFalse()
        ->and($file?->error())->toBe(UPLOAD_ERR_INI_SIZE)
        ->and($file?->mimeType())->toBe('application/octet-stream')
        ->and(fn () => $file?->store('x'))->toThrow(RuntimeException::class, 'código 1');
});
