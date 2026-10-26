<?php

/**
 * Router.php
 *
 * Gestiona el registro, agrupación, resolución y ejecución de rutas HTTP en el framework BootZen.
 * Permite definir rutas, grupos, middlewares y resolver peticiones entrantes, integrando el contenedor de dependencias.
 * Implementa los patrones Router y Chain of Responsibility, facilitando la extensión y modularidad del flujo HTTP.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.1.0
 * @since      1.0.0
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

use Closure;
use InvalidArgumentException;

/**
 * Clase Router
 *
 * Encapsula la lógica de enrutamiento HTTP, permitiendo el registro de rutas, grupos, middlewares y la
 * resolución de peticiones.
 * Utiliza el contenedor de dependencias para instanciar controladores y middlewares, soportando rutas
 * dinámicas y parámetros.
 *
 * Consideraciones de seguridad: permite asociar middlewares de autenticación, autorización y CORS a rutas y grupos.
 * Consideraciones de rendimiento: soporta rutas cacheables y ejecución eficiente de la cadena de middlewares.
 *
 */
class Router
{
    /**
     * Colección de rutas registradas, organizadas por método HTTP.
     *
     * @var array<string, Route[]>
     */
    private array $routes = [];

    /**
     * Middleware global aplicado a las rutas.
     *
     * @var array<int, string|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * Pila de grupos de rutas para atributos compartidos (prefix, middleware, etc.).
     *
     * @var array<int, array<string, mixed>>
     */
    private array $groupStack = [];

    /**
     * Contenedor de dependencias para resolver controladores y middleware.
     *
     * @var Container
     */
    private Container $container;

    /**
     * Constructor de Router
     *
     * @param Container $container Contenedor de dependencias
     * @example $router = new Router($container);
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Registra una ruta GET
     *
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la ruta creada
     * @example $router->get('/home', 'HomeController@index');
     */
    public function get(string $path, mixed $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Registra una ruta POST
     *
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la ruta creada
     * @example $router->post('/user', 'UserController@store');
     */
    public function post(string $path, mixed $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Registra una ruta PUT
     *
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la ruta creada
     * @example $router->put('/user/{id}', 'UserController@update');
     */
    public function put(string $path, mixed $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Registra una ruta PATCH
     *
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la ruta creada
     * @example $router->patch('/user/{id}', 'UserController@patch');
     */
    public function patch(string $path, mixed $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Registra una ruta DELETE
     *
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la ruta creada
     * @example $router->delete('/user/{id}', 'UserController@destroy');
     */
    public function delete(string $path, mixed $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Registra una ruta para múltiples métodos HTTP
     *
     * @param array<string> $methods Métodos HTTP
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la última ruta creada
     * @throws InvalidArgumentException Si no se especifican métodos
     * @example $router->match(['GET', 'POST'], '/contact', 'ContactController@handle');
     */
    public function match(array $methods, string $path, mixed $handler): Route
    {
        if (empty($methods)) {
            throw new InvalidArgumentException('Debe especificar al menos un método HTTP.');
        }
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $path, $handler);
        }

        // $route está garantizado como Route aquí
        return $route;
    }

    /**
     * Registra una ruta para todos los métodos HTTP estándar
     *
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la ruta creada
     * @example $router->any('/ping', fn() => 'pong');
     */
    public function any(string $path, mixed $handler): Route
    {
        return $this->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $path, $handler);
    }

    /**
     * Crea un grupo de rutas con atributos compartidos (prefix, middleware, etc.)
     *
     * @param array<string, mixed> $attributes Atributos del grupo
     * @param Closure $callback Callback que define las rutas del grupo
     * @return void
     * @example
     *   $router->group(['prefix' => '/admin', 'middleware' => 'auth'], function($router) {
     *       $router->get('/dashboard', 'AdminController@index');
     *   });
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Aplica middleware global a las siguientes rutas
     *
     * @param string|array<string> $middleware Middleware(s) global(es)
     * @return self Instancia modificada
     * @example $router->middleware('auth');
     * @example $router->middleware(['auth', 'log']);
     */
    public function middleware(string|array $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }

    /**
     * Añade una ruta a la colección interna
     *
     * @internal
     * @param string $method Método HTTP
     * @param string $path Path de la ruta
     * @param mixed $handler Handler asociado
     * @return Route Instancia de la ruta creada
     */
    private function addRoute(string $method, string $path, mixed $handler): Route
    {
        $path = $this->getGroupPrefix() . $path;
        $middleware = array_merge($this->getGroupMiddleware(), $this->middleware);

        $route = new Route($method, $path, $handler, $middleware);

        $this->routes[$method][] = $route;

        // Reinicia el middleware global para la siguiente ruta
        $this->middleware = [];

        return $route;
    }

    /**
     * Obtiene el prefijo actual de los grupos anidados
     *
     * @internal
     * @return string Prefijo concatenado
     */
    private function getGroupPrefix(): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }

        return $prefix;
    }

    /**
     * Obtiene el middleware de los grupos anidados
     *
     * @internal
     * @return array<int, string|MiddlewareInterface> Middlewares de grupo
     */
    private function getGroupMiddleware(): array
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $groupMiddleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middleware = array_merge($middleware, $groupMiddleware);
            }
        }

        return $middleware;
    }

    /**
     * Resuelve la petición HTTP actual y ejecuta la ruta correspondiente
     *
     * @param Request $request Instancia de la petición
     * @return Response Respuesta generada
     * @since 1.0.0
     * @throws InvalidArgumentException Si el handler o middleware no es válido
     * @example $response = $router->resolve($request);
     */
    public function resolve(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Maneja peticiones OPTIONS para CORS
        if ($method === 'OPTIONS') {
            return $this->handleOptions($request);
        }

        // Busca la ruta coincidente
        $route = $this->findRoute($method, $path);

        if ($route === null) {
            return Response::notFound('Route not found');
        }

        // Asigna parámetros de ruta al request
        $request = $this->setRouteParameters($request, $route, $path);

        // Ejecuta la cadena de middlewares
        return $this->executeMiddleware($route->getMiddleware(), $request, function ($request) use ($route) {
            return $this->callHandler($route->getHandler(), $request, $route->getParameters());
        });
    }

    /**
     * Busca una ruta coincidente por método y path
     *
     * @internal
     * @param string $method Método HTTP
     * @param string $path Path solicitado
     * @return Route|null Ruta encontrada o null
     */
    private function findRoute(string $method, string $path): ?Route
    {
        if (! isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            if ($this->matchRoute($route->getPath(), $path)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Verifica si el patrón de ruta coincide con el path solicitado
     *
     * @internal
     * @param string $pattern Patrón de ruta
     * @param string $path Path solicitado
     * @return bool true si coincide
     */
    private function matchRoute(string $pattern, string $path): bool
    {
        // Convierte el patrón de ruta a regex
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        return preg_match($regex, $path) === 1;
    }

    /**
     * Asigna los parámetros dinámicos extraídos del path a la ruta
     *
     * @internal
     * @param Request $request Instancia de la petición
     * @param Route $route Ruta encontrada
     * @param string $path Path solicitado
     * @return Request Instancia de la petición (posiblemente modificada)
     */
    private function setRouteParameters(Request $request, Route $route, string $path): Request
    {
        $pattern = $route->getPath();

        // Extrae nombres de parámetros
        preg_match_all('/\{([^}]+)\}/', $pattern, $paramNames);

        // Extrae valores de parámetros
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        preg_match($regex, $path, $paramValues);

        // Elimina el match completo
        array_shift($paramValues);

        // Combina nombres y valores
        $parameters = [];
        foreach ($paramNames[1] as $index => $name) {
            $parameters[$name] = $paramValues[$index] ?? null;
        }

        $route->setParameters($parameters);

        return $request;
    }

    /**
     * Ejecuta la cadena de middlewares y finalmente el handler de la ruta
     *
     * @internal
     * @param array<int, string|MiddlewareInterface> $middleware Middlewares a ejecutar
     * @param Request $request Instancia de la petición
     * @param Closure $final Callback final (handler de la ruta)
     * @return Response Respuesta generada
     * @throws InvalidArgumentException Si el middleware no es válido
     */
    private function executeMiddleware(array $middleware, Request $request, Closure $final): Response
    {
        if (empty($middleware)) {
            return $final($request);
        }

        $middlewareClass = array_shift($middleware);

        // Resuelve el middleware desde el contenedor
        if (is_string($middlewareClass)) {
            $middlewareInstance = $this->container->make($middlewareClass);
        } else {
            $middlewareInstance = $middlewareClass;
        }

        if (! $middlewareInstance instanceof MiddlewareInterface) {
            throw new InvalidArgumentException("El middleware debe implementar MiddlewareInterface");
        }

        return $middlewareInstance->process($request, function ($request) use ($middleware, $final) {
            return $this->executeMiddleware($middleware, $request, $final);
        });
    }

    /**
     * Llama al handler de la ruta y convierte el resultado a Response si es necesario
     *
     * @internal
     * @param mixed $handler Handler asociado
     * @param Request $request Instancia de la petición
     * @param array<string, mixed> $parameters Parámetros de ruta
     * @return Response Respuesta generada
     * @throws InvalidArgumentException Si el handler no es válido
     */
    private function callHandler(mixed $handler, Request $request, array $parameters = []): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request, ...$parameters);
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$controller, $method] = explode('@', $handler, 2);
            $controllerInstance = $this->container->make($controller);
            $result = $controllerInstance->$method($request, ...$parameters);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$controller, $method] = $handler;
            $controllerInstance = $this->container->make($controller);
            $result = $controllerInstance->$method($request, ...$parameters);
        } elseif (is_callable($handler)) {
            $result = $handler($request, ...$parameters);
        } else {
            throw new InvalidArgumentException("Handler de ruta inválido");
        }

        // Convierte el resultado a Response si es necesario
        if (! $result instanceof Response) {
            if (is_array($result)) {
                return Response::json($result);
            } elseif (is_string($result)) {
                return Response::html($result);
            } else {
                return Response::html((string) $result);
            }
        }

        return $result;
    }

    /**
     * Maneja peticiones OPTIONS para CORS
     *
     * @internal
     * @param Request $request Instancia de la petición
     * @return Response Respuesta generada
     */
    private function handleOptions(Request $request): Response
    {
        // Intenta resolver CorsMiddleware desde el contenedor
        if ($this->container->has(\BootZen\Middleware\CorsMiddleware::class)) {
            $cors = $this->container->make(\BootZen\Middleware\CorsMiddleware::class);

            return $cors->process($request, fn ($req) => new Response(200, ''));
        }

        // Fallback: respuesta genérica sin headers
        return new Response(200, '');
    }

    /**
     * Obtiene todas las rutas registradas
     *
     * @return array<string, Route[]> Colección de rutas
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
