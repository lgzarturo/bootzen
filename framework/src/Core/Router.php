<?php

/**
 * Router.php
 *
 * Registro y resolución de rutas HTTP en BootZen: métodos, grupos, rutas con nombre, recursos CRUD,
 * restricciones de parámetros y respuestas 404/405 automáticas.
 *
 * Los parámetros de ruta se guardan en el Request (Request::param()) y se inyectan por nombre
 * en el handler mediante Container::call().
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      1.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use Closure;
use InvalidArgumentException;
use Stringable;
use UnexpectedValueException;

/**
 * Router HTTP.
 *
 * Formatos de handler aceptados:
 * - `[Controlador::class, 'metodo']` (recomendado)
 * - Closure, para respuestas triviales
 * - Clase invocable (`Controlador::class` con __invoke)
 *
 * @example $router->get('/tareas/{id:\d+}', [TareaController::class, 'show'])->name('tareas.show');
 */
class Router
{
    /**
     * Acciones de resource(): acción => [métodos, sufijo de path].
     */
    private const RESOURCE_ACTIONS = [
        'index' => [['GET'], ''],
        'create' => [['GET'], '/create'],
        'store' => [['POST'], ''],
        'show' => [['GET'], '/{id}'],
        'edit' => [['GET'], '/{id}/edit'],
        'update' => [['PUT', 'PATCH'], '/{id}'],
        'destroy' => [['DELETE'], '/{id}'],
    ];

    /**
     * Rutas en orden de registro (la primera que coincide gana).
     *
     * @var list<Route>
     */
    private array $routes = [];

    /**
     * Atributos de los grupos abiertos (prefix, middleware, as).
     *
     * @var list<array{prefix?: string, middleware?: string|MiddlewareInterface|list<string|MiddlewareInterface>, as?: string}>
     */
    private array $groupStack = [];

    /**
     * Índice de rutas por nombre, se reconstruye bajo demanda.
     *
     * @var array<string, Route>|null
     */
    private ?array $nameIndex = null;

    private readonly Pipeline $pipeline;

    public function __construct(private readonly Container $container, ?Pipeline $pipeline = null)
    {
        $this->pipeline = $pipeline ?? new Pipeline($container);
    }

    public function get(string $path, mixed $handler): Route
    {
        return $this->addRoute(['GET'], $path, $handler);
    }

    public function post(string $path, mixed $handler): Route
    {
        return $this->addRoute(['POST'], $path, $handler);
    }

    public function put(string $path, mixed $handler): Route
    {
        return $this->addRoute(['PUT'], $path, $handler);
    }

    public function patch(string $path, mixed $handler): Route
    {
        return $this->addRoute(['PATCH'], $path, $handler);
    }

    public function delete(string $path, mixed $handler): Route
    {
        return $this->addRoute(['DELETE'], $path, $handler);
    }

    public function options(string $path, mixed $handler): Route
    {
        return $this->addRoute(['OPTIONS'], $path, $handler);
    }

    /**
     * Registra una ruta para varios métodos.
     *
     * @param list<string> $methods
     * @throws InvalidArgumentException Si la lista de métodos está vacía
     */
    public function match(array $methods, string $path, mixed $handler): Route
    {
        if ($methods === []) {
            throw new InvalidArgumentException('Debe especificar al menos un método HTTP.');
        }

        return $this->addRoute($methods, $path, $handler);
    }

    /**
     * Registra una ruta para todos los métodos comunes.
     */
    public function any(string $path, mixed $handler): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler);
    }

    /**
     * Agrupa rutas con atributos comunes.
     *
     * Atributos: 'prefix' (path), 'middleware' (clase, alias, grupo o lista) y 'as' (prefijo de nombre).
     *
     * @param array{prefix?: string, middleware?: string|MiddlewareInterface|list<string|MiddlewareInterface>, as?: string} $attributes
     * @param Closure(Router): void $callback Registra las rutas del grupo
     * @example $router->group(['prefix' => '/admin', 'as' => 'admin.', 'middleware' => 'auth'], function (Router $router): void {
     *     $router->get('/', [AdminController::class, 'index'])->name('index'); // admin.index
     * });
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    /**
     * Registra las rutas CRUD de un recurso, con nombres '{nombre}.{accion}'.
     *
     * | Acción  | Método     | Path                 |
     * | ------- | ---------- | -------------------- |
     * | index   | GET        | /{nombre}            |
     * | create  | GET        | /{nombre}/create     |
     * | store   | POST       | /{nombre}            |
     * | show    | GET        | /{nombre}/{id}       |
     * | edit    | GET        | /{nombre}/{id}/edit  |
     * | update  | PUT, PATCH | /{nombre}/{id}       |
     * | destroy | DELETE     | /{nombre}/{id}       |
     *
     * @param string $name Nombre del recurso, también es el path (ej. 'tareas')
     * @param string $controller Clase del controlador con un método por acción
     * @param array{only?: list<string>, except?: list<string>} $options Acciones a incluir o excluir
     * @return array<string, Route> Rutas registradas por acción
     * @throws InvalidArgumentException Si se indica una acción desconocida
     */
    public function resource(string $name, string $controller, array $options = []): array
    {
        $actions = array_keys(self::RESOURCE_ACTIONS);
        $requested = [...($options['only'] ?? []), ...($options['except'] ?? [])];

        foreach ($requested as $action) {
            if (! in_array($action, $actions, true)) {
                throw new InvalidArgumentException(
                    "Acción [{$action}] desconocida en resource(). Usa: " . implode(', ', $actions) . '.'
                );
            }
        }

        if (isset($options['only'])) {
            $actions = array_values(array_intersect($actions, $options['only']));
        }

        $actions = array_values(array_diff($actions, $options['except'] ?? []));
        $base = '/' . trim($name, '/');
        $routeName = str_replace('/', '.', trim($name, '/'));
        $routes = [];

        foreach ($actions as $action) {
            [$methods, $suffix] = self::RESOURCE_ACTIONS[$action];
            $routes[$action] = $this->addRoute($methods, $base . $suffix, [$controller, $action])
                ->name($routeName . '.' . $action);
        }

        return $routes;
    }

    /**
     * Registra un alias de middleware.
     *
     * @example $router->aliasMiddleware('cors', CorsMiddleware::class);
     */
    public function aliasMiddleware(string $name, string $class): void
    {
        $this->pipeline->alias($name, $class);
    }

    /**
     * Registra un grupo de middleware.
     *
     * @param list<string|MiddlewareInterface> $middleware
     * @example $router->middlewareGroup('api', ['cors']);
     */
    public function middlewareGroup(string $name, array $middleware): void
    {
        $this->pipeline->group($name, $middleware);
    }

    public function getPipeline(): Pipeline
    {
        return $this->pipeline;
    }

    /**
     * Resuelve la petición: busca la ruta, ejecuta su middleware y el handler.
     *
     * @throws HttpException 404 si ninguna ruta coincide, 405 si el path existe con otros métodos
     */
    public function resolve(Request $request): Response
    {
        $path = self::normalizePath($request->getPath());
        $method = $request->getMethod();
        $allowed = [];
        $firstMatch = null;

        foreach ($this->routes as $route) {
            $parameters = $route->matches($path);
            if ($parameters === null) {
                continue;
            }

            if (! $route->allowsMethod($method)) {
                array_push($allowed, ...$route->getMethods());
                $firstMatch ??= [$route, $parameters];

                continue;
            }

            $request = $request->withRoute($route)->withAttributes($parameters);

            return $this->pipeline->run(
                $route->getMiddleware(),
                $request,
                fn (Request $request): Response => $this->dispatch($route, $request)
            );
        }

        if ($allowed === []) {
            throw new HttpException(404);
        }

        $allowed = array_values(array_unique([...$allowed, 'OPTIONS']));
        if (in_array('GET', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        $allowHeader = ['Allow' => implode(', ', $allowed)];

        if ($method === 'OPTIONS' && $firstMatch !== null) {
            // El preflight pasa por el middleware de la ruta (por ejemplo 'cors') antes del 204 automático
            [$route, $parameters] = $firstMatch;

            return $this->pipeline->run(
                $route->getMiddleware(),
                $request->withRoute($route)->withAttributes($parameters),
                static fn (): Response => new Response(204, '', $allowHeader)
            );
        }

        throw new HttpException(405, '', $allowHeader);
    }

    /**
     * Genera el path de una ruta con nombre.
     *
     * @param array<string, string|int|float|bool|Stringable> $parameters Parámetros; los sobrantes van al query string
     * @throws InvalidArgumentException Si la ruta no existe o faltan parámetros
     * @example $router->url('tareas.show', ['id' => 5]); // /tareas/5
     */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->getRoute($name);

        if ($route === null) {
            throw new InvalidArgumentException("No existe una ruta con el nombre [{$name}].");
        }

        return $route->url($parameters);
    }

    /**
     * Busca una ruta por nombre.
     */
    public function getRoute(string $name): ?Route
    {
        if ($this->nameIndex === null) {
            $this->nameIndex = [];
            foreach ($this->routes as $route) {
                $routeName = $route->getName();
                if ($routeName !== null && ! isset($this->nameIndex[$routeName])) {
                    $this->nameIndex[$routeName] = $route;
                }
            }
        }

        return $this->nameIndex[$name] ?? null;
    }

    /**
     * Todas las rutas registradas, en orden.
     *
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Normaliza un path: empieza con '/', sin barras repetidas y sin '/' final (salvo la raíz).
     */
    public static function normalizePath(string $path): string
    {
        return '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
    }

    /**
     * @param list<string> $methods
     * @throws InvalidArgumentException Si el handler no tiene un formato válido
     */
    private function addRoute(array $methods, string $path, mixed $handler): Route
    {
        $this->assertValidHandler($handler, $path);

        $prefix = '';
        $namePrefix = '';
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim($group['prefix'] ?? '', '/');
            $namePrefix .= $group['as'] ?? '';

            if (isset($group['middleware'])) {
                $groupMiddleware = $group['middleware'];
                array_push($middleware, ...(is_array($groupMiddleware) ? $groupMiddleware : [$groupMiddleware]));
            }
        }

        $route = new Route($methods, self::normalizePath($prefix . '/' . $path), $handler, $middleware, $namePrefix);

        $this->routes[] = $route;
        $this->nameIndex = null;

        return $route;
    }

    /**
     * Ejecuta el handler de la ruta y convierte su resultado en Response.
     *
     * @throws UnexpectedValueException Si el handler retorna un tipo que no se puede convertir
     */
    private function dispatch(Route $route, Request $request): Response
    {
        $handler = $route->getHandler();

        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0]) && is_string($handler[1])) {
            $handler = [$handler[0], $handler[1]];
        } elseif (! $handler instanceof Closure && ! is_string($handler)) {
            throw new UnexpectedValueException("Handler inválido en la ruta [{$route->getPath()}].");
        }

        $result = $this->container->call($handler, $request->params(), [$request]);

        return match (true) {
            $result instanceof Response => $result,
            is_array($result), $result instanceof \JsonSerializable => Response::json($result),
            $result === null => Response::noContent(),
            is_string($result), is_int($result), is_float($result), $result instanceof Stringable => Response::html((string) $result),
            default => throw new UnexpectedValueException(sprintf(
                'El handler de [%s] retornó %s; retorna un Response, un string o un array.',
                $route->getPath(),
                get_debug_type($result)
            )),
        };
    }

    /**
     * @throws InvalidArgumentException Si el handler no tiene un formato válido
     */
    private function assertValidHandler(mixed $handler, string $path): void
    {
        if ($handler instanceof Closure) {
            return;
        }

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0] ?? null) && is_string($handler[1] ?? null)) {
            return;
        }

        if (is_string($handler) && ! str_contains($handler, '@') && class_exists($handler) && method_exists($handler, '__invoke')) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "Handler inválido en la ruta [%s]. Usa [Controlador::class, 'metodo'], un Closure o una clase invocable.",
            $path
        ));
    }
}
