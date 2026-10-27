<?php

declare(strict_types=1);

use BootZen\Core\Config;

beforeEach(function (): void {
    $this->directory = makeTempDir();
    writeFile($this->directory . '/config/app.php', "<?php return ['name' => 'Demo', 'mail' => ['from' => 'a@b.c']];");
    writeFile($this->directory . '/config/cache.php', "<?php return ['ttl' => 60];");
});

afterEach(function (): void {
    removeDir($this->directory);
});

it('carga cada archivo con su nombre como primer segmento', function (): void {
    $config = Config::fromDirectory($this->directory . '/config');

    expect($config->get('app.name'))->toBe('Demo')
        ->and($config->get('app.mail.from'))->toBe('a@b.c')
        ->and($config->get('cache.ttl'))->toBe(60)
        ->and($config->get('app.falta', 'def'))->toBe('def')
        ->and($config->has('app.mail'))->toBeTrue()
        ->and($config->has('app.otro'))->toBeFalse();
});

it('permite asignar valores en tiempo de ejecución', function (): void {
    $config = new Config();
    $config->set('app.debug', true);
    $config->set('app.nested.key', 'valor');

    expect($config->get('app'))->toBe(['debug' => true, 'nested' => ['key' => 'valor']]);
});

it('rechaza archivos que no retornan un array', function (): void {
    writeFile($this->directory . '/config/roto.php', '<?php return "texto";');

    Config::fromDirectory($this->directory . '/config');
})->throws(RuntimeException::class, 'debe retornar un array');

it('prefiere la caché cuando existe', function (): void {
    $cacheFile = $this->directory . '/storage/cache/config.php';
    Config::fromDirectory($this->directory . '/config')->writeCache($cacheFile);
    writeFile($this->directory . '/config/app.php', "<?php return ['name' => 'Cambiado'];");

    expect(Config::load($this->directory . '/config', $cacheFile)->get('app.name'))->toBe('Demo');

    unlink($cacheFile);

    expect(Config::load($this->directory . '/config', $cacheFile)->get('app.name'))->toBe('Cambiado');
});
