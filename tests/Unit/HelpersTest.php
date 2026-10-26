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
    new BootZen\Core\Application(BOOTZEN_BASE_PATH);

    expect(base_path('database/app.sqlite'))->toBe(BOOTZEN_BASE_PATH . '/database/app.sqlite')
        ->and(storage_path('logs'))->toBe(BOOTZEN_BASE_PATH . '/storage/logs');
});

it('env() convierte los valores especiales', function (string $raw, mixed $expected): void {
    $_ENV['BZ_TEST_CAST'] = $raw;

    expect(env('BZ_TEST_CAST'))->toBe($expected);

    forgetEnv('BZ_TEST_CAST');
})->with([
    ['true', true],
    ['(false)', false],
    ['null', null],
    ['empty', ''],
    ['texto', 'texto'],
]);

it('env() devuelve el valor por defecto si la variable no existe', function (): void {
    expect(env('BZ_TEST_NO_EXISTE', 'def'))->toBe('def');
});

it('logger() escribe en el log de la aplicación', function (): void {
    $project = makeTempDir();
    new BootZen\Core\Application($project);

    logger('Pedido {id} listo', 'notice', ['id' => 7]);

    expect((string) file_get_contents($project . '/storage/logs/app-' . date('Y-m-d') . '.log'))
        ->toContain('app.NOTICE: Pedido 7 listo');

    removeDir($project);
});

it('cache() y app() exponen los servicios de la aplicación', function (): void {
    $project = makeTempDir();
    $app = new BootZen\Core\Application($project);

    cache()->set('clave', 'valor');

    expect(cache()->get('clave'))->toBe('valor')
        ->and(app())->toBe($app)
        ->and(app(BootZen\Core\Router::class))->toBe($app->getRouter());

    removeDir($project);
});
