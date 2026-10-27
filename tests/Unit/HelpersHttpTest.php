<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\HttpException;

beforeEach(function (): void {
    $this->project = makeTempDir();
    writeFile($this->project . '/config/app.php', "<?php return ['url' => 'https://mi-app.test/'];");
    writeFile($this->project . '/public/assets/app.css', 'body{}');
    new Application($this->project);
});

afterEach(function (): void {
    removeDir($this->project);
});

it('genera URLs absolutas con la URL de la configuración', function (): void {
    expect(url('tareas'))->toBe('https://mi-app.test/tareas')
        ->and(url('/'))->toBe('https://mi-app.test/')
        ->and(public_path('assets/app.css'))->toBe($this->project . '/public/assets/app.css');
});

it('versiona los assets que existen con su fecha de modificación', function (): void {
    $version = filemtime($this->project . '/public/assets/app.css');

    expect(asset('assets/app.css'))->toBe('https://mi-app.test/assets/app.css?v=' . $version)
        ->and(asset('assets/no-existe.js'))->toBe('https://mi-app.test/assets/no-existe.js');
});

it('crea respuestas JSON y de descarga', function (): void {
    $file = $this->project . '/storage/reporte.csv';
    writeFile($file, "id,total\n1,10\n");

    $json = json_response(['ok' => true], 201, ['X-Id' => '7']);
    $download = download($file, 'ventas.csv');

    expect($json->getStatusCode())->toBe(201)
        ->and($json->getHeader('x-id'))->toBe('7')
        ->and($download->getBody())->toBe("id,total\n1,10\n")
        ->and($download->getHeader('content-disposition'))->toBe('attachment; filename="ventas.csv"')
        ->and($download->getHeader('content-length'))->toBe('14')
        ->and(mime_type($file))->toStartWith('text/')
        ->and(mime_type($this->project . '/nada.bin'))->toBe('application/octet-stream');
});

it('responde 404 al descargar un archivo inexistente', function (): void {
    download($this->project . '/nada.pdf');
})->throws(HttpException::class, 'File not found.');

it('dd() vuelca los valores y detiene la ejecución', function (): void {
    ob_start();

    try {
        dd(['clave' => 'valor']);
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } finally {
        $output = (string) ob_get_clean();
    }

    expect($error ?? null)->toBe('Ejecución detenida por dd().')
        ->and($output)->toContain('"clave"')
        ->and($output)->toContain('"valor"');
});

it('aplana y agrupa arreglos', function (): void {
    $users = [['rol' => 'admin', 'n' => 1], ['rol' => 'user', 'n' => 2], ['rol' => 'admin', 'n' => 3]];

    expect(array_flatten([[1, [2]], [3, 4]]))->toBe([1, 2, 3, 4])
        ->and(array_group_by($users, static fn (array $u): string => $u['rol']))->toBe([
            'admin' => [['rol' => 'admin', 'n' => 1], ['rol' => 'admin', 'n' => 3]],
            'user' => [['rol' => 'user', 'n' => 2]],
        ]);
});

it('detecta peticiones AJAX y la IP del cliente desde $_SERVER', function (): void {
    $server = $_SERVER;
    $_SERVER = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 198.51.100.8'];

    $ajax = is_ajax();
    $ip = get_client_ip();
    $_SERVER = ['REMOTE_ADDR' => '127.0.0.1'];
    $unknown = get_client_ip();
    $_SERVER = $server;

    expect([$ajax, $ip, $unknown])->toBe([true, '198.51.100.8', 'unknown']);
});
