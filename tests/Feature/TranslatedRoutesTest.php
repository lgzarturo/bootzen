<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Router;

final class TranslatedRoutesController
{
    public function show(int $id): string
    {
        return __('tareas') . '|' . route('tareas.show', ['id' => $id]) . '|' . locale_url('es') . "\n" . hreflang_links();
    }
}

function translatedRoutesApp(string $project, ?Closure $routes = null): Application
{
    $app = new Application($project);
    $app->routes($routes ?? static function (Router $router): void {
        $router->localized(static function (Router $router): void {
            $router->get('/tareas/{id}', [TranslatedRoutesController::class, 'show'])->name('tareas.show');
            $router->post('/tareas', static fn (): string => 'creada')->name('tareas.store');
            $router->group(['prefix' => '/cuenta'], static function (Router $router): void {
                $router->get('/perfil', static fn (): string => 'perfil')->name('perfil');
            });
        });
    });

    return $app;
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-rutas');
    writeFile($this->project . '/config/app.php', "<?php return ['locale' => 'es', 'locales' => 'es,en', 'url' => 'https://tienda.mx'];");
    writeFile($this->project . '/lang/en/rutas.php', "<?php return ['tareas' => 'tasks', 'cuenta' => 'account', 'perfil' => 'profile', 'mal/segmento' => 'x'];");
    writeFile($this->project . '/lang/en.php', "<?php return ['tareas' => 'tasks (texto)'];");
    $this->app = translatedRoutesApp($this->project);
    $this->get = fn (string $uri) => $this->app->run(new Request('GET', $uri));
});

afterEach(fn () => removeDir($this->project));

it('traduce los segmentos en el idioma con prefijo y deja el de por defecto igual', function (): void {
    expect(($this->get)('/tareas/7')->getBody())->toStartWith('tareas|/tareas/7|/tareas/7')
        ->and(($this->get)('/en/tasks/7')->getBody())->toStartWith('tasks (texto)|/en/tasks/7|/tareas/7')
        ->and(($this->get)('/en/account/profile')->getBody())->toBe('perfil')
        ->and($this->app->run(new Request('POST', '/en/tasks'))->getBody())->toBe('creada')
        ->and(($this->get)('/en/tasks/7')->getBody())
        ->toContain('<link rel="alternate" hreflang="en" href="https://tienda.mx/en/tasks/7">')
        ->toContain('<link rel="alternate" hreflang="es" href="https://tienda.mx/tareas/7">');
});

it('redirige con 301 la URL sin traducir, con parámetros y query', function (): void {
    $response = ($this->get)('/en/tareas/7?ver=todo');

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeader('location'))->toBe('/en/tasks/7?ver=todo')
        ->and(($this->get)('/en/cuenta/perfil')->getHeader('location'))->toBe('/en/account/profile')
        // Solo las rutas GET dejan redirección: un POST a la URL vieja no existe
        ->and($this->app->run(new Request('POST', '/en/tareas'))->getStatusCode())->toBe(404);
});

it('avisa si dos rutas quedan en la misma URL al traducir', function (): void {
    writeFile($this->project . '/lang/en/rutas.php', "<?php return ['pedidos' => 'orders', 'ordenes' => 'orders'];");

    expect(fn () => translatedRoutesApp($this->project, static function (Router $router): void {
        $router->localized(static function (Router $router): void {
            $router->get('/pedidos', static fn (): string => 'a')->name('pedidos');
            $router->get('/ordenes', static fn (): string => 'b')->name('ordenes');
        });
    }))->toThrow(LogicException::class, 'Al traducir /en/ordenes queda /en/orders, que ya usa otra ruta. Revisa lang/en/rutas.php.');
});

it('sin rutas.php todo queda como antes', function (): void {
    unlink($this->project . '/lang/en/rutas.php');
    $app = translatedRoutesApp($this->project);

    expect($app->run(new Request('GET', '/en/tareas/3'))->getStatusCode())->toBe(200)
        ->and($app->run(new Request('GET', '/en/tasks/3'))->getStatusCode())->toBe(404);
});
