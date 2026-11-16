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

beforeEach(fn () => Request::setTrustedProxies([]));
afterEach(fn () => Request::setTrustedProxies([]));

it('detecta HTTPS por variable o puerto, y por X-Forwarded-Proto solo desde un proxy de confianza', function (array $server, array $headers, array $proxies, bool $secure): void {
    Request::setTrustedProxies($proxies);

    expect((new Request('GET', '/', $headers, [], [], $server))->isSecure())->toBe($secure);
})->with([
    'HTTPS on' => [['HTTPS' => 'on'], [], [], true],
    'puerto 443' => [['SERVER_PORT' => 443], [], [], true],
    'proxy de confianza' => [['REMOTE_ADDR' => '10.0.0.1'], ['X-Forwarded-Proto' => 'https'], ['private'], true],
    'cabecera sin proxies configurados' => [['REMOTE_ADDR' => '10.0.0.1'], ['X-Forwarded-Proto' => 'https'], [], false],
    'cabecera desde una IP que no es proxy' => [['REMOTE_ADDR' => '203.0.113.9'], ['X-Forwarded-Proto' => 'https'], ['private'], false],
    'HTTP plano' => [['HTTPS' => 'off', 'SERVER_PORT' => 80], [], [], false],
]);

it('sin proxies de confianza la IP es la de la conexión, diga lo que diga X-Forwarded-For', function (array $server, string $ip): void {
    expect((new Request('GET', '/', [], [], [], $server))->getClientIp())->toBe($ip);
})->with([
    'conexión privada con cabecera' => [['HTTP_X_FORWARDED_FOR' => '198.51.100.4', 'REMOTE_ADDR' => '10.0.0.1'], '10.0.0.1'],
    'loopback con cabecera' => [['HTTP_X_FORWARDED_FOR' => '198.51.100.4', 'REMOTE_ADDR' => '127.0.0.1'], '127.0.0.1'],
    'conexión pública' => [['REMOTE_ADDR' => '203.0.113.9'], '203.0.113.9'],
    'conexión pública con cabecera' => [['HTTP_X_FORWARDED_FOR' => '198.51.100.4', 'REMOTE_ADDR' => '203.0.113.9'], '203.0.113.9'],
    'sin REMOTE_ADDR' => [[], '127.0.0.1'],
]);

it('con proxies de confianza toma la última IP de X-Forwarded-For que no es un proxy', function (array $proxies, array $server, string $ip): void {
    Request::setTrustedProxies($proxies);

    expect((new Request('GET', '/', [], [], [], $server))->getClientIp())->toBe($ip);
})->with([
    'el cliente inventa el primer valor' => [['private'], ['HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9', 'REMOTE_ADDR' => '172.18.0.1'], '203.0.113.9'],
    'dos proxies encadenados' => [['private'], ['HTTP_X_FORWARDED_FOR' => '203.0.113.9, 10.0.0.7', 'REMOTE_ADDR' => '127.0.0.1'], '203.0.113.9'],
    'conexión directa que no es proxy' => [['private'], ['HTTP_X_FORWARDED_FOR' => '1.2.3.4', 'REMOTE_ADDR' => '198.51.100.1'], '198.51.100.1'],
    'proxy sin cabecera' => [['private'], ['REMOTE_ADDR' => '10.0.0.1'], '10.0.0.1'],
    'valor que no es una IP' => [['private'], ['HTTP_X_FORWARDED_FOR' => '<script>, 10.0.0.2', 'REMOTE_ADDR' => '10.0.0.1'], '10.0.0.2'],
    'rango CIDR' => [['203.0.113.0/24'], ['HTTP_X_FORWARDED_FOR' => '9.9.9.9', 'REMOTE_ADDR' => '203.0.113.77'], '9.9.9.9'],
    'fuera del rango CIDR' => [['203.0.113.0/24'], ['HTTP_X_FORWARDED_FOR' => '9.9.9.9', 'REMOTE_ADDR' => '203.0.114.1'], '203.0.114.1'],
    'IP exacta' => [['10.0.0.5'], ['HTTP_X_FORWARDED_FOR' => '9.9.9.9', 'REMOTE_ADDR' => '10.0.0.5'], '9.9.9.9'],
    'IPv6' => [['2001:db8::/32'], ['HTTP_X_FORWARDED_FOR' => '2606:4700::1', 'REMOTE_ADDR' => '2001:db8::5'], '2606:4700::1'],
    'comodín' => [['*'], ['HTTP_X_FORWARDED_FOR' => '9.9.9.9', 'REMOTE_ADDR' => '198.51.100.1'], '9.9.9.9'],
]);
