<?php

declare(strict_types=1);

use BootZen\Core\Cache;

beforeEach(function (): void {
    $this->directory = makeTempDir();
    $this->cache = new Cache($this->directory . '/data');
});

afterEach(function (): void {
    removeDir($this->directory);
});

it('guarda y recupera valores', function (): void {
    $this->cache->set('usuario', ['id' => 1, 'nombre' => 'Ana']);

    expect($this->cache->get('usuario'))->toBe(['id' => 1, 'nombre' => 'Ana'])
        ->and($this->cache->has('usuario'))->toBeTrue()
        ->and($this->cache->get('falta', 'def'))->toBe('def');
});

it('expira los valores', function (): void {
    $this->cache->set('temporal', 'x', -1);

    expect($this->cache->has('temporal'))->toBeFalse()
        ->and($this->cache->get('temporal'))->toBeNull();
});

it('calcula solo una vez con remember', function (): void {
    $calls = 0;
    $callback = function () use (&$calls): int {
        return ++$calls;
    };

    expect($this->cache->remember('contador', 60, $callback))->toBe(1)
        ->and($this->cache->remember('contador', 60, $callback))->toBe(1)
        ->and($calls)->toBe(1);
});

it('incrementa contadores', function (): void {
    expect($this->cache->increment('visitas'))->toBe(1)
        ->and($this->cache->increment('visitas', 5))->toBe(6);
});

it('olvida claves y vacía la caché', function (): void {
    $this->cache->set('a', 1);
    $this->cache->set('b', 2);
    $this->cache->forget('a');

    expect($this->cache->has('a'))->toBeFalse()->and($this->cache->has('b'))->toBeTrue();

    $this->cache->flush();

    expect($this->cache->has('b'))->toBeFalse();
});

it('descarta archivos corruptos', function (): void {
    $this->cache->set('dato', 'ok');
    file_put_contents($this->directory . '/data/' . sha1('dato') . '.cache', 'basura');

    expect($this->cache->get('dato', 'def'))->toBe('def');
});
