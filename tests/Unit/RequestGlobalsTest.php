<?php

declare(strict_types=1);

use BootZen\Core\Request;

beforeEach(function (): void {
    $this->globals = [$_SERVER, $_POST, $_FILES, $_COOKIE];
});

afterEach(function (): void {
    [$_SERVER, $_POST, $_FILES, $_COOKIE] = $this->globals;
});

it('construye la petición desde las variables globales', function (): void {
    $_SERVER = [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/contacto?ref=home',
        'HTTP_ACCEPT' => 'text/html',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'CONTENT_LENGTH' => '15',
        'REMOTE_ADDR' => '203.0.113.7',
    ];
    $_POST = ['nombre' => 'Ana'];
    $_FILES = ['avatar' => ['name' => 'foto.png', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 10]];
    $_COOKIE = ['tema' => 'oscuro'];

    $request = Request::fromGlobals();

    expect($request->getMethod())->toBe('POST')
        ->and($request->getUri())->toBe('/contacto?ref=home')
        ->and($request->getPath())->toBe('/contacto')
        ->and($request->getQuery())->toBe(['ref' => 'home'])
        ->and($request->getHeader('accept'))->toBe('text/html')
        ->and($request->getHeader('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and($request->getHeader('content-length'))->toBe('15')
        ->and($request->getHeaders())->toHaveKey('x-requested-with')
        ->and($request->isAjax())->toBeTrue()
        ->and($request->getBody())->toBe(['nombre' => 'Ana'])
        ->and($request->getFile('avatar'))->toMatchArray(['name' => 'foto.png'])
        ->and($request->getFile('otro'))->toBeNull()
        ->and($request->getFiles())->toHaveKey('avatar')
        ->and($request->getCookie('tema'))->toBe('oscuro')
        ->and($request->getCookie('falta', 'claro'))->toBe('claro')
        ->and($request->getServer('REMOTE_ADDR'))->toBe('203.0.113.7')
        ->and($request->getServer('FALTA', 'x'))->toBe('x')
        ->and($request->getServer())->toHaveKey('REQUEST_URI');
});

it('lee el cuerpo JSON y detecta la petición como JSON', function (): void {
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api', 'CONTENT_TYPE' => 'application/json'];

    $request = Request::fromGlobals('{"titulo":"Comprar pan","hecho":false}');

    expect($request->isJson())->toBeTrue()
        ->and($request->wantsJson())->toBeTrue()
        ->and($request->getBody())->toBe(['titulo' => 'Comprar pan', 'hecho' => false]);
});

it('ignora cuerpos JSON inválidos o que no son objetos', function (string $raw): void {
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api', 'CONTENT_TYPE' => 'application/json'];

    expect(Request::fromGlobals($raw)->getBody())->toBe([]);
})->with(['{roto', '123', '"texto"', '']);

it('usa valores por defecto si faltan las variables del servidor', function (): void {
    $_SERVER = [];
    $_POST = [];

    $request = Request::fromGlobals();

    expect($request->getMethod())->toBe('GET')
        ->and($request->getPath())->toBe('/')
        ->and($request->getClientIp())->toBe('127.0.0.1');
});

it('detecta HTTPS por variable, puerto o proxy', function (array $server, array $headers, bool $secure): void {
    expect((new Request('GET', '/', $headers, [], [], $server))->isSecure())->toBe($secure);
})->with([
    'HTTPS on' => [['HTTPS' => 'on'], [], true],
    'puerto 443' => [['SERVER_PORT' => 443], [], true],
    'proxy' => [[], ['X-Forwarded-Proto' => 'https'], true],
    'HTTP plano' => [['HTTPS' => 'off', 'SERVER_PORT' => 80], [], false],
]);

it('obtiene la IP pública del cliente ignorando rangos privados', function (array $server, string $ip): void {
    expect((new Request('GET', '/', [], [], [], $server))->getClientIp())->toBe($ip);
})->with([
    'proxy con IP pública' => [['HTTP_X_FORWARDED_FOR' => '198.51.100.4, 10.0.0.1', 'REMOTE_ADDR' => '10.0.0.1'], '198.51.100.4'],
    'solo IP privada' => [['HTTP_X_FORWARDED_FOR' => '192.168.1.5', 'REMOTE_ADDR' => '10.0.0.2'], '10.0.0.2'],
    'remote addr pública' => [['REMOTE_ADDR' => '203.0.113.9'], '203.0.113.9'],
    'cabecera falsa sin proxy' => [['HTTP_X_FORWARDED_FOR' => '198.51.100.4', 'REMOTE_ADDR' => '203.0.113.9'], '203.0.113.9'],
]);
