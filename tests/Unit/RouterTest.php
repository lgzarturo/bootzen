<?php

declare(strict_types=1);

use BootZen\Core\Container;
use BootZen\Core\HttpException;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;

final class RouterTestController
{
    public function index(): string
    {
        return 'index';
    }

    public function create(): string
    {
        return 'create';
    }

    public function store(): string
    {
        return 'store';
    }

    public function show(Request $request, int $id): string
    {
        return 'show ' . $id . ' ' . get_debug_type($id) . ' ' . $request->getMethod();
    }

    public function edit(int $id): string
    {
        return "edit {$id}";
    }

    public function update(int $id): string
    {
        return "update {$id}";
    }

    public function destroy(int $id): string
    {
        return "destroy {$id}";
    }
}

final class RouterTestInvokable
{
    public function __invoke(): string
    {
        return 'invocable';
    }
}

final class RouterTestTagMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $tag = 'm')
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response->withBody($response->getBody() . "+{$this->tag}");
    }
}

function routerFor(callable $define): Router
{
    $router = new Router(new Container());
    $define($router);

    return $router;
}

function dispatchTo(Router $router, string $method, string $uri, array $body = []): Response
{
    return $router->resolve(new Request($method, $uri, [], $body));
}

it('despacha closures y convierte arrays a JSON', function (): void {
    $router = routerFor(fn (Router $r) => $r->get('/api', static fn (): array => ['ok' => true]));

    expect(dispatchTo($router, 'GET', '/api')->getBody())->toBe('{"ok":true}');
});

it('inyecta parámetros de ruta por nombre con su tipo y el Request por tipo', function (): void {
    $router = routerFor(fn (Router $r) => $r->get('/tareas/{id:\d+}', [RouterTestController::class, 'show']));

    expect(dispatchTo($router, 'GET', '/tareas/42')->getBody())->toBe('show 42 int GET');
});

it('acepta clases invocables', function (): void {
    $router = routerFor(fn (Router $r) => $r->get('/inv', RouterTestInvokable::class));

    expect(dispatchTo($router, 'GET', '/inv')->getBody())->toBe('invocable');
});

it('rechaza handlers con el formato Clase@metodo', function (): void {
    routerFor(fn (Router $r) => $r->get('/x', 'RouterTestController@index'));
})->throws(InvalidArgumentException::class, 'Handler inválido');

it('ignora la barra final y las barras repetidas', function (): void {
    $router = routerFor(fn (Router $r) => $r->get('/tareas', static fn (): string => 'ok'));

    expect(dispatchTo($router, 'GET', '/tareas/')->getBody())->toBe('ok')
        ->and(dispatchTo($router, 'GET', '//tareas')->getBody())->toBe('ok');
});

it('aplica prefijo, nombre y middleware de grupos anidados', function (): void {
    $router = routerFor(function (Router $r): void {
        $r->group(['prefix' => '/admin', 'as' => 'admin.', 'middleware' => new RouterTestTagMiddleware('a')], function (Router $r): void {
            $r->group(['prefix' => 'usuarios', 'as' => 'usuarios.', 'middleware' => [new RouterTestTagMiddleware('b')]], function (Router $r): void {
                $r->get('/', static fn (): string => 'lista')->name('index');
            });
        });
    });

    expect(dispatchTo($router, 'GET', '/admin/usuarios')->getBody())->toBe('lista+b+a')
        ->and($router->url('admin.usuarios.index'))->toBe('/admin/usuarios');
});

it('registra las siete rutas de un recurso con sus nombres', function (): void {
    $router = routerFor(fn (Router $r) => $r->resource('tareas', RouterTestController::class));

    expect(dispatchTo($router, 'GET', '/tareas')->getBody())->toBe('index')
        ->and(dispatchTo($router, 'GET', '/tareas/create')->getBody())->toBe('create')
        ->and(dispatchTo($router, 'POST', '/tareas')->getBody())->toBe('store')
        ->and(dispatchTo($router, 'GET', '/tareas/3/edit')->getBody())->toBe('edit 3')
        ->and(dispatchTo($router, 'PUT', '/tareas/3')->getBody())->toBe('update 3')
        ->and(dispatchTo($router, 'PATCH', '/tareas/3')->getBody())->toBe('update 3')
        ->and(dispatchTo($router, 'DELETE', '/tareas/3')->getBody())->toBe('destroy 3')
        ->and($router->url('tareas.edit', ['id' => 3]))->toBe('/tareas/3/edit');
});

it('filtra las acciones de un recurso con only y except', function (): void {
    $router = new Router(new Container());

    expect(array_keys($router->resource('fotos', RouterTestController::class, ['only' => ['index', 'show']])))
        ->toBe(['index', 'show'])
        ->and(array_keys($router->resource('notas', RouterTestController::class, ['except' => ['create', 'edit']])))
        ->toBe(['index', 'store', 'show', 'update', 'destroy']);
});

it('rechaza acciones de recurso desconocidas', function (): void {
    (new Router(new Container()))->resource('x', RouterTestController::class, ['only' => ['listar']]);
})->throws(InvalidArgumentException::class, 'Acción [listar] desconocida');

it('simula PUT y DELETE desde formularios con _method', function (): void {
    $router = routerFor(fn (Router $r) => $r->delete('/tareas/{id}', static fn (string $id): string => "borrada {$id}"));

    expect(dispatchTo($router, 'POST', '/tareas/9', ['_method' => 'DELETE'])->getBody())->toBe('borrada 9');
});

it('lanza 404 si ninguna ruta coincide', function (): void {
    dispatchTo(new Router(new Container()), 'GET', '/nada');
})->throws(HttpException::class);

it('lanza 405 con la cabecera Allow si el path existe con otro método', function (): void {
    $router = routerFor(function (Router $r): void {
        $r->get('/tareas', static fn (): string => 'x');
        $r->post('/tareas', static fn (): string => 'y');
    });

    try {
        dispatchTo($router, 'DELETE', '/tareas');
        $this->fail('Se esperaba HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(405)
            ->and($e->getHeaders())->toBe(['Allow' => 'GET, POST, OPTIONS, HEAD']);
    }
});

it('responde OPTIONS automáticamente con los métodos permitidos', function (): void {
    $router = routerFor(fn (Router $r) => $r->post('/contacto', static fn (): string => 'x'));
    $response = dispatchTo($router, 'OPTIONS', '/contacto');

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getHeader('allow'))->toBe('POST, OPTIONS');
});

it('atiende HEAD con las rutas GET', function (): void {
    $router = routerFor(fn (Router $r) => $r->get('/', static fn (): string => 'hola'));

    expect(dispatchTo($router, 'HEAD', '/')->getStatusCode())->toBe(200);
});

it('convierte null en 204 y rechaza tipos que no puede convertir', function (): void {
    $router = routerFor(function (Router $r): void {
        $r->get('/vacio', static fn () => null);
        $r->get('/objeto', static fn (): object => new stdClass());
    });

    expect(dispatchTo($router, 'GET', '/vacio')->getStatusCode())->toBe(204);

    dispatchTo($router, 'GET', '/objeto');
})->throws(UnexpectedValueException::class, 'retornó stdClass');

it('expone la ruta resuelta y sus parámetros en el Request', function (): void {
    $router = routerFor(fn (Router $r) => $r->get('/u/{slug}', static fn (Request $request): array => [
        'nombre' => $request->getRoute()?->getName(),
        'slug' => $request->param('slug'),
        'params' => $request->params(),
    ])->name('perfil'));

    expect(json_decode(dispatchTo($router, 'GET', '/u/ana')->getBody(), true))
        ->toBe(['nombre' => 'perfil', 'slug' => 'ana', 'params' => ['slug' => 'ana']]);
});

it('falla al generar la URL de una ruta inexistente', function (): void {
    (new Router(new Container()))->url('no.existe');
})->throws(InvalidArgumentException::class, 'No existe una ruta con el nombre [no.existe]');

it('resuelve alias y grupos de middleware en las rutas', function (): void {
    $router = routerFor(function (Router $r): void {
        $r->aliasMiddleware('tag', RouterTestTagMiddleware::class);
        $r->middlewareGroup('web', ['tag']);
        $r->get('/', static fn (): string => 'ok')->middleware('web');
    });

    expect(dispatchTo($router, 'GET', '/')->getBody())->toBe('ok+m');
});
