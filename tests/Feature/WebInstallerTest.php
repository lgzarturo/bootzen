<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Deploy\WebInstaller;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-instalar');
    $this->config = function (bool $installer, string $key = 'base64:clave-del-servidor'): void {
        writeFile($this->project . '/config/app.php', '<?php return ' . var_export(['name' => 'Tienda', 'key' => $key, 'installer' => $installer], true) . ';');
    };
    ($this->config)(true);
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => __DIR__ . '/../database/app.sqlite']]];");
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear_notas.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Migration;

        return new class () extends Migration {
            public function up(Connection $db): void
            {
                $db->exec('CREATE TABLE notas (id INTEGER PRIMARY KEY)');
            }
        };
        PHP);

    $this->send = function (string $method, array $body = []): Response {
        return (new Application($this->project))->run(new Request($method, WebInstaller::PATH, [], $body));
    };
});

afterEach(fn () => removeDir($this->project));

it('no existe si INSTALL_ENABLED está apagado', function (): void {
    ($this->config)(false);

    expect(($this->send)('GET')->getStatusCode())->toBe(404);
});

it('pide definir la APP_KEY antes de instalar', function (): void {
    ($this->config)(true, '');

    $response = ($this->send)('POST', ['key' => '']);

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getBody())->toContain('Falta la APP_KEY');
});

it('muestra el formulario sin caché ni indexación', function (): void {
    $response = ($this->send)('GET');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('cache-control'))->toBe('no-store')
        ->and($response->getBody())->toContain('<title>Instalar Tienda</title>')
        ->toContain('name="robots" content="noindex"')
        ->toContain('<input type="password" name="key"');
});

it('rechaza una clave incorrecta y limita los intentos', function (): void {
    for ($i = 0; $i < 5; $i++) {
        expect(($this->send)('POST', ['key' => 'otra'])->getStatusCode())->toBe(403);
    }

    $blocked = ($this->send)('POST', ['key' => 'base64:clave-del-servidor']);

    expect($blocked->getStatusCode())->toBe(429)
        ->and($blocked->getBody())->toContain('Demasiados intentos. Espera 10 minutos.')
        ->and($this->project . '/' . WebInstaller::LOCK_FILE)->not->toBeFile();
});

it('avisa si no puede bloquearse', function (): void {
    writeFile($this->project . '/storage', 'un archivo donde debería haber una carpeta');

    $response = ($this->send)('POST', ['key' => 'base64:clave-del-servidor']);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->toContain('la página sigue abierta')
        ->toContain('<li>2026_01_01_000000_crear_notas</li>');
});

it('migra, se bloquea y desde ahí responde 404', function (): void {
    $response = ($this->send)('POST', ['key' => 'base64:clave-del-servidor']);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody())->toContain('Instalación lista')
        ->toContain('<li>2026_01_01_000000_crear_notas</li>')
        ->toContain('INSTALL_ENABLED=false')
        ->and((string) file_get_contents($this->project . '/' . WebInstaller::LOCK_FILE))->toContain('2026_01_01_000000_crear_notas')
        ->and(($this->send)('GET')->getStatusCode())->toBe(404)
        ->and(($this->send)('POST', ['key' => 'base64:clave-del-servidor'])->getStatusCode())->toBe(404);

    unlink($this->project . '/' . WebInstaller::LOCK_FILE);

    expect(($this->send)('POST', ['key' => 'base64:clave-del-servidor'])->getBody())->toContain('No había migraciones pendientes');
});

it('muestra el error si la base no responde y no se bloquea', function (): void {
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => __DIR__]]];");

    $response = ($this->send)('POST', ['key' => 'base64:clave-del-servidor']);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->toContain('No se pudo instalar')
        ->and($this->project . '/' . WebInstaller::LOCK_FILE)->not->toBeFile();
});
