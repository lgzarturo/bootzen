<?php

/**
 * Route.php
 *
 * Representa una ruta individual en el framework BootZen, asociando método HTTP, path, handler y middlewares.
 * Permite la definición, consulta y manipulación de rutas, soportando middlewares y parámetros dinámicos.
 * Implementa el patrón Route y es utilizada por el router principal del framework.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.5
 * @since      1.0.0
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

/**
 * Clase Route
 *
 * Encapsula la información de una ruta HTTP, incluyendo método, path, handler, middlewares y parámetros.
 * Permite la integración de middlewares y la gestión de parámetros dinámicos en el framework BootZen.
 *
 * Consideraciones de seguridad: permite asociar middlewares de autenticación, autorización y validación.
 * Consideraciones de rendimiento: soporta rutas inmutables y cacheables para optimizar el enrutamiento.
 *
 */
class Route
{
    /**
     * Método HTTP de la ruta (GET, POST, etc.)
     *
     * @var string
     */
    private string $method;

    /**
     * Path o patrón de la ruta
     *
     * @var string
     */
    private string $path;

    /**
     * Handler asociado a la ruta (callable, controlador, etc.)
     *
     * @var mixed
     */
    private mixed $handler;

    /**
     * Middlewares asociados a la ruta
     *
     * @var array<int, string|MiddlewareInterface>
     */
    private array $middleware;

    /**
     * Parámetros dinámicos extraídos del path
     *
     * @var array<string, mixed>
     */
    private array $parameters = [];

    /**
     * Constructor de Route
     *
     * @param string $method Método HTTP
     * @param string $path Path o patrón de la ruta
     * @param mixed $handler Handler asociado (callable, controlador, etc.)
     * @param array<int, string|MiddlewareInterface> $middleware Middlewares asociados
     * @example $route = new Route('GET', '/user/{id}', 'UserController@show');
     */
    public function __construct(string $method, string $path, mixed $handler, array $middleware = [])
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
    }

    /**
     * Obtiene el método HTTP de la ruta
     *
     * @return string Método HTTP
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Obtiene el path o patrón de la ruta
     *
     * @return string Path de la ruta
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Obtiene el handler asociado a la ruta
     *
     * @return mixed Handler (callable, controlador, etc.)
     */
    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * Obtiene los middlewares asociados a la ruta
     *
     * @return array<int, string|MiddlewareInterface> Array de middlewares
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Obtiene los parámetros dinámicos de la ruta
     *
     * @return array<string, mixed> Parámetros extraídos
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Asigna los parámetros dinámicos de la ruta
     *
     * @param array<string, mixed> $parameters Parámetros a asignar
     * @return void
     * @example $route->setParameters(['id' => 5]);
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * Añade uno o varios middlewares a la ruta
     *
     * @param string|array<int, string|MiddlewareInterface> $middleware Middleware(s) a añadir
     * @return self Instancia modificada
     * @example $route->middleware('auth');
     * @example $route->middleware(['auth', 'log']);
     */
    public function middleware(string|array $middleware): self
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        $this->middleware = array_merge($this->middleware, $middleware);

        return $this;
    }
}
