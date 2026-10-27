<?php

declare(strict_types=1);

use BootZen\Core\Env;

it('interpreta valores con y sin comillas', function (): void {
    $values = Env::parse(<<<'ENV'
        # Comentario
        APP_NAME=Mi App
        export APP_ENV=local
        SIMPLE='literal ${APP_NAME} \n'
        DOBLE="línea\tcon tab"
        CON_COMENTARIO=valor # se ignora
        VACIO=
        URL=https://ejemplo.com/#ancla
        INTERPOLADO="${APP_NAME} (${APP_ENV})"
        ENV);

    expect($values)->toBe([
        'APP_NAME' => 'Mi App',
        'APP_ENV' => 'local',
        'SIMPLE' => 'literal ${APP_NAME} \n',
        'DOBLE' => "línea\tcon tab",
        'CON_COMENTARIO' => 'valor',
        'VACIO' => '',
        'URL' => 'https://ejemplo.com/#ancla',
        'INTERPOLADO' => 'Mi App (local)',
    ]);
});

it('admite comillas escapadas dentro de comillas dobles', function (): void {
    expect(Env::parse('FRASE="dijo \"hola\""'))->toBe(['FRASE' => 'dijo "hola"']);
});

it('rechaza líneas inválidas y comillas sin cerrar', function (string $contents): void {
    Env::parse($contents);
})->throws(RuntimeException::class)->with([
    'sin igual' => ['ESTO NO ES VALIDO'],
    'comilla abierta' => ['CLAVE="sin cerrar'],
]);

it('carga el archivo sin sobrescribir variables existentes', function (): void {
    $directory = makeTempDir();
    writeFile($directory . '/.env', "BZ_TEST_NUEVA=desde-archivo\nBZ_TEST_EXISTENTE=desde-archivo\n");
    $_ENV['BZ_TEST_EXISTENTE'] = 'real';

    Env::load($directory . '/.env');

    expect($_ENV['BZ_TEST_NUEVA'])->toBe('desde-archivo')
        ->and(getenv('BZ_TEST_NUEVA'))->toBe('desde-archivo')
        ->and($_ENV['BZ_TEST_EXISTENTE'])->toBe('real');

    forgetEnv('BZ_TEST_NUEVA', 'BZ_TEST_EXISTENTE');
    removeDir($directory);
});

it('ignora un archivo inexistente', function (): void {
    expect(Env::load('/no/existe/.env'))->toBe([]);
});
