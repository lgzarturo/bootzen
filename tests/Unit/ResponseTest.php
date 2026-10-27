<?php

declare(strict_types=1);

use BootZen\Core\Response;

it('crea respuestas HTML', function (): void {
    $response = Response::html('<h1>Hola</h1>');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('content-type'))->toBe('text/html; charset=utf-8')
        ->and($response->getBody())->toBe('<h1>Hola</h1>');
});

it('crea respuestas JSON', function (): void {
    $response = Response::json(['ok' => true], 201);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeader('content-type'))->toBe('application/json')
        ->and($response->getBody())->toBe('{"ok":true}');
});

it('crea redirecciones', function (): void {
    $response = Response::redirect('/login');

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('location'))->toBe('/login');
});

it('es inmutable al agregar cabeceras', function (): void {
    $original = Response::html('x');
    $copia = $original->withHeader('X-Test', '1');

    expect($original->getHeader('x-test'))->toBeNull()
        ->and($copia->getHeader('x-test'))->toBe('1');
});

it('crea respuestas sin contenido y agrega varias cabeceras', function (): void {
    $response = Response::noContent()->withHeaders(['Cache-Control' => 'no-store', 'X-A' => '1']);

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getBody())->toBe('')
        ->and($response->getHeader('cache-control'))->toBe('no-store')
        ->and($response->getHeader('x-a'))->toBe('1');
});

it('codifica listas y caracteres especiales en JSON sin escapar', function (): void {
    expect(Response::json([['url' => 'https://a.b/c', 'nombre' => 'Peña']])->getBody())
        ->toBe('[{"url":"https://a.b/c","nombre":"Peña"}]');
});

it('crea las respuestas de error comunes', function (string $factory, int $status, string $body): void {
    $response = Response::$factory();

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getBody())->toBe($body)
        ->and($response->getStatusText())->toBe($body);
})->with([
    ['notFound', 404, 'Not Found'],
    ['serverError', 500, 'Internal Server Error'],
    ['unauthorized', 401, 'Unauthorized'],
    ['forbidden', 403, 'Forbidden'],
    ['badRequest', 400, 'Bad Request'],
    ['tooManyRequests', 429, 'Too Many Requests'],
]);

it('clasifica el código de estado', function (int $status, array $expected): void {
    $response = new Response($status);

    expect([
        $response->isSuccessful(),
        $response->isRedirect(),
        $response->isClientError(),
        $response->isServerError(),
    ])->toBe($expected);
})->with([
    [204, [true, false, false, false]],
    [302, [false, true, false, false]],
    [422, [false, false, true, false]],
    [503, [false, false, false, true]],
]);

it('cambia estado y cuerpo sin mutar el original', function (): void {
    $original = Response::html('a');
    $copia = $original->withStatus(201)->withBody('b');

    expect([$original->getStatusCode(), $original->getBody()])->toBe([200, 'a'])
        ->and([$copia->getStatusCode(), $copia->getBody()])->toBe([201, 'b'])
        ->and((new Response(299))->getStatusText())->toBe('Unknown Status')
        ->and($copia->getHeaders())->toBe(['content-type' => 'text/html; charset=utf-8']);
});

it('envía estado, cabeceras, cookies y cuerpo', function (): void {
    $response = Response::html('<p>listo</p>', 201)
        ->withHeader('X-Test', '1')
        ->withCookie('sesion', 'abc', time() + 60, '/', '', true, true, 'Strict');

    ob_start();
    $response->send();
    $output = ob_get_clean();

    expect($output)->toBe('<p>listo</p>')
        ->and(http_response_code())->toBe(201);

    http_response_code(200);
});
