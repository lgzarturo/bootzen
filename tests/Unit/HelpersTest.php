<?php

declare(strict_types=1);

use BootZen\Core\HttpException;
use BootZen\Core\Response;

it('escapa HTML con e()', function (): void {
    expect(e('<script>"x"</script>'))->toBe('&lt;script&gt;&quot;x&quot;&lt;/script&gt;')
        ->and(e(42))->toBe('42')
        ->and(e(null))->toBe('');
});

it('genera slugs', function (string $texto, string $esperado): void {
    expect(slugify($texto))->toBe($esperado);
})->with([
    ['Hola Mundo!', 'hola-mundo'],
    ['  Árbol  de   navidad ', 'arbol-de-navidad'],
    ['!!!', 'n-a'],
]);

it('genera cadenas aleatorias de la longitud pedida', function (): void {
    expect(str_random(24))->toHaveLength(24)
        ->and(str_random(0))->toBe('');
});

it('formatea bytes', function (): void {
    expect(format_bytes(0))->toBe('0.00 B')
        ->and(format_bytes(2048))->toBe('2.00 KB')
        ->and(format_bytes(1536, 1))->toBe('1.5 KB');
});

it('redirect() retorna una respuesta en vez de terminar la ejecución', function (): void {
    $response = redirect('/login', 301);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(301);
});

it('abort() lanza HttpException con su código', function (): void {
    abort(403, 'Sin permiso');
})->throws(HttpException::class, 'Sin permiso');

it('resuelve rutas relativas a la raíz del proyecto', function (): void {
    expect(base_path('database/app.sqlite'))->toBe(BOOTZEN_BASE_PATH . '/database/app.sqlite')
        ->and(storage_path('logs'))->toBe(BOOTZEN_BASE_PATH . '/storage/logs');
});
