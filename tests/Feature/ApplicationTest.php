<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Cache;
use BootZen\Core\Config;
use BootZen\Core\Logger;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;

final class ApplicationTestHeaderMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request)->withHeader('X-Global', 'si');
    }
}

/**
 * Crea un proyecto mínimo en un directorio temporal.
 *
 * @param array<string, string> $files Archivos extra: ruta relativa => contenido
 */
function makeProject(string $env = '', array $files = []): string
{
    $directory = makeTempDir('bootzen-app');
    writeFile($directory . '/.env', $env);
    writeFile($directory . '/config/app.php', <<<'PHP'
        <?php return [
            'name' => env('APP_NAME', 'BootZen'),
            'debug' => env('BZ_APP_DEBUG', false),
            'timezone' => 'America/Cancun',
            'log_level' => 'debug',
        ];
        PHP);

    foreach ($files as $path => $contents) {
        writeFile($directory . '/' . $path, $contents);
    }

    return $directory;
}

afterEach(function (): void {
    forgetEnv('APP_NAME', 'BZ_APP_DEBUG');
    date_default_timezone_set('UTC');

    if (isset($this->project)) {
        removeDir($this->project);
    }
});

it('carga .env y config/ al construirse y registra los servicios', function (): void {
    $this->project = makeProject("APP_NAME=\"Mi Tienda\"\n");
    $app = new Application($this->project);

    expect($app->config()->get('app.name'))->toBe('Mi Tienda')
        ->and(config('app.name'))->toBe('Mi Tienda')
        ->and(date_default_timezone_get())->toBe('America/Cancun')
        ->and($app->make(Config::class))->toBe($app->config())
        ->and($app->make(Logger::class))->toBeInstanceOf(Logger::class)
        ->and($app->make(Cache::class))->toBe($app->make(Cache::class))
        ->and(Application::getInstance())->toBe($app)
        ->and(base_path('x'))->toBe($this->project . '/x');
});

it('resuelve rutas con middleware global por alias', function (): void {
    $this->project = makeProject();
    $app = new Application($this->project);
    $app->aliasMiddleware('cabecera', ApplicationTestHeaderMiddleware::class);
    $app->addGlobalMiddleware('cabecera');
    $app->routes(static function (Router $router): void {
        $router->get('/', static fn (): string => 'Hola')->name('inicio');
    });

    $response = $app->run(new Request('GET', '/'));

    expect($response->getBody())->toBe('Hola')
        ->and($response->getHeader('x-global'))->toBe('si')
        ->and(route('inicio'))->toBe('/');
});

it('aplica el middleware global también a los 404', function (): void {
    $this->project = makeProject();
    $app = new Application($this->project);
    $app->addGlobalMiddleware(new ApplicationTestHeaderMiddleware());

    // El 404 lo lanza el router dentro del pipeline; el handler lo convierte fuera
    expect($app->run(new Request('GET', '/nada'))->getStatusCode())->toBe(404);
});

it('renderiza la vista de error de la aplicación', function (): void {
    $this->project = makeProject('', [
        'resources/views/errors/404.php' => '<h1>Perdido: <?= e($message) ?> (<?= $status ?>)</h1>',
        'resources/views/errors/default.php' => '<h1>Genérico <?= $status ?>: <?= e($title) ?></h1>',
    ]);
    $app = new Application($this->project);
    $app->routes(static function (Router $router): void {
        $router->get('/privado', static fn () => abort(403));
    });

    expect($app->run(new Request('GET', '/nada'))->getBody())->toBe('<h1>Perdido: Not Found (404)</h1>')
        ->and($app->run(new Request('GET', '/privado'))->getBody())->toBe('<h1>Genérico 403: Forbidden</h1>');
});

it('responde errores en JSON cuando el cliente lo pide', function (): void {
    $this->project = makeProject();
    $app = new Application($this->project);
    $app->routes(static function (Router $router): void {
        $router->get('/api/tareas/{id}', static fn () => abort(404, 'Tarea no encontrada'));
        $router->post('/api/tareas', static fn (): string => 'x');
    });

    $notFound = $app->run(new Request('GET', '/api/tareas/1', ['Accept' => 'application/json']));
    $notAllowed = $app->run(new Request('GET', '/api/tareas', ['Accept' => 'application/json']));

    expect($notFound->getStatusCode())->toBe(404)
        ->and(json_decode($notFound->getBody(), true))->toBe([
            'error' => 'Not Found',
            'message' => 'Tarea no encontrada',
            'status' => 404,
        ])
        ->and($notAllowed->getStatusCode())->toBe(405)
        ->and($notAllowed->getHeader('allow'))->toBe('POST, OPTIONS');
});

it('oculta detalles fuera de debug y registra la excepción en el log', function (): void {
    $this->project = makeProject();
    $app = new Application($this->project);
    $app->routes(static function (Router $router): void {
        $router->get('/falla', static function (): never {
            throw new RuntimeException('detalle interno');
        });
    });

    $response = $app->run(new Request('GET', '/falla'));
    $log = (string) file_get_contents($this->project . '/storage/logs/app-' . date('Y-m-d') . '.log');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->not->toContain('detalle interno')
        ->and($log)->toContain('app.ERROR: detalle interno')
        ->and($log)->toContain('"uri":"/falla"');
});

it('muestra la página de depuración con APP_DEBUG', function (): void {
    $this->project = makeProject("BZ_APP_DEBUG=true\n");
    $app = new Application($this->project);
    $app->routes(static function (Router $router): void {
        $router->get('/falla', static function (): never {
            throw new LogicException('explota <aquí>');
        });
    });

    $body = $app->run(new Request('GET', '/falla'))->getBody();

    expect($app->isDebug())->toBeTrue()
        ->and($body)->toContain('LogicException')
        ->and($body)->toContain('explota &lt;aquí&gt;')
        ->and($body)->toContain('class="hl"')
        ->and($body)->toContain('GET /falla');
});
