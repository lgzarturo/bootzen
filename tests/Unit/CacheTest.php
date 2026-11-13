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

it('guarda los archivos solo para su dueño', function (): void {
    $this->cache->set('dato', 'ok');

    expect(fileperms($this->directory . '/data/' . sha1('dato') . '.cache') & 0777)->toBe(0600);
});

it('con clave firma cada archivo y descarta sin deserializar el que no lleva la firma', function (): void {
    $cache = new Cache($this->directory . '/firmada', 'clave-de-la-app');
    $cache->set('dato', ['fecha' => new DateTimeImmutable('2026-01-01')]);

    expect($cache->get('dato')['fecha'])->toBeInstanceOf(DateTimeImmutable::class)
        // Otra aplicación (otra clave) no lee la entrada
        ->and((new Cache($this->directory . '/firmada', 'otra-clave'))->get('dato', 'def'))->toBe('def');

    // Un archivo plantado por otro usuario del servidor: bien formado pero sin firma
    $cache->set('plantado', 'original');
    file_put_contents($this->directory . '/firmada/' . sha1('plantado') . '.cache', serialize(['expires' => null, 'value' => 'falso']));

    expect($cache->get('plantado', 'def'))->toBe('def');
});

it('ejecuta atomic con candado y deja usar increment dentro', function (): void {
    $this->cache->increment('contador');

    expect($this->cache->atomic('contador', fn (): int => $this->cache->increment('contador')))->toBe(2)
        ->and($this->cache->atomic('contador', fn (): string => 'valor'))->toBe('valor')
        ->and((new Cache(null))->atomic('x', fn (): int => 7))->toBe(7);
});

it('no pierde incrementos entre procesos simultáneos', function (): void {
    $script = $this->directory . '/sumar.php';
    file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__, 2) . '/framework/bootstrap.php', true) . ';'
        . '$c = new BootZen\\Core\\Cache($argv[1]); for ($i = 0; $i < 50; $i++) { $c->increment("n", 1, 60); }');

    $procesos = [];
    for ($i = 0; $i < 4; $i++) {
        $procesos[] = proc_open([PHP_BINARY, $script, $this->directory . '/data'], [], $pipes);
    }
    foreach ($procesos as $proceso) {
        proc_close($proceso);
    }

    expect($this->cache->get('n'))->toBe(200);
});
