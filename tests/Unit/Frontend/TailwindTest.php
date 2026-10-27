<?php

declare(strict_types=1);

use BootZen\Frontend\Tailwind;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-tw');
});

afterEach(function (): void {
    removeDir($this->project);
});

/**
 * Descargador falso: sirve el archivo de sumas y un binario de prueba.
 *
 * @param array<int, string> $urls Registro de URLs pedidas
 */
function fakeDownloader(string $binary, string $sumsFor, array &$urls, ?string $sumsHash = null): Closure
{
    return static function (string $url, string $destination) use ($binary, $sumsFor, &$urls, $sumsHash): void {
        $urls[] = $url;
        $contents = str_ends_with($url, 'sha256sums.txt')
            ? ($sumsHash ?? hash('sha256', $binary)) . "  ./{$sumsFor}\n" . str_repeat('0', 64) . "  ./otro\n"
            : $binary;
        file_put_contents($destination, $contents);
    };
}

it('elige el binario según el sistema y la arquitectura', function (string $os, string $machine, bool $musl, string $asset): void {
    expect((new Tailwind('/p', $os, $machine, $musl))->assetName())->toBe($asset);
})->with([
    ['Linux', 'x86_64', false, 'tailwindcss-linux-x64'],
    ['Linux', 'aarch64', false, 'tailwindcss-linux-arm64'],
    ['Linux', 'x86_64', true, 'tailwindcss-linux-x64-musl'],
    ['Darwin', 'arm64', false, 'tailwindcss-macos-arm64'],
    ['Windows', 'AMD64', false, 'tailwindcss-windows-x64.exe'],
]);

it('explica qué hacer en plataformas sin binario oficial', function (string $os, string $machine): void {
    (new Tailwind('/p', $os, $machine))->assetName();
})->throws(RuntimeException::class, 'npm')->with([
    ['Linux', 'riscv64'],
    ['Windows', 'arm64'],
    ['SunOS', 'x86_64'],
]);

it('descarga el binario, verifica su SHA-256 y lo deja ejecutable', function (): void {
    $urls = [];
    $tailwind = new Tailwind($this->project, 'Linux', 'x86_64', false, fakeDownloader('binario-falso', 'tailwindcss-linux-x64', $urls));

    $path = $tailwind->install();

    expect($path)->toBe($this->project . '/storage/bin/tailwindcss-' . Tailwind::VERSION)
        ->and(file_get_contents($path))->toBe('binario-falso')
        ->and(is_executable($path))->toBeTrue()
        ->and($tailwind->isInstalled())->toBeTrue()
        ->and($urls[0])->toEndWith('/' . Tailwind::VERSION . '/sha256sums.txt')
        ->and($urls[1])->toEndWith('/' . Tailwind::VERSION . '/tailwindcss-linux-x64')
        ->and(glob($this->project . '/storage/bin/*.download'))->toBe([]);
});

it('no instala un binario cuyo SHA-256 no coincide', function (): void {
    $urls = [];
    $tailwind = new Tailwind($this->project, 'Linux', 'x86_64', false, fakeDownloader('alterado', 'tailwindcss-linux-x64', $urls, str_repeat('a', 64)));

    expect(fn () => $tailwind->install())->toThrow(RuntimeException::class, 'no coincide')
        ->and($tailwind->isInstalled())->toBeFalse()
        ->and(glob($this->project . '/storage/bin/*'))->toBe([]);
});

it('falla si el release no publica el checksum del binario', function (): void {
    $urls = [];
    $tailwind = new Tailwind($this->project, 'Darwin', 'arm64', false, fakeDownloader('x', 'tailwindcss-linux-x64', $urls));

    $tailwind->install();
})->throws(RuntimeException::class, 'no incluye el checksum de tailwindcss-macos-arm64');

it('prefiere el CLI de node_modules si el proyecto lo instaló con npm', function (): void {
    $tailwind = new Tailwind($this->project, 'Linux', 'x86_64');

    expect($tailwind->executable())->toBe($tailwind->binaryPath());

    writeFile($this->project . '/node_modules/.bin/tailwindcss', '#!/bin/sh');

    expect($tailwind->executable())->toBe($this->project . '/node_modules/.bin/tailwindcss');
});

it('arma los argumentos de compilación y de watch', function (): void {
    $tailwind = new Tailwind($this->project, 'Linux', 'x86_64');

    expect($tailwind->buildArguments())->toBe([
        $tailwind->binaryPath(),
        '--input',
        $this->project . '/resources/css/app.css',
        '--output',
        $this->project . '/public/assets/app.css',
        '--minify',
    ])->and($tailwind->buildArguments(true)[5])->toBe('--watch');
});
