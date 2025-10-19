<?php

/**
 * Núcleo de la aplicación BootZen
 *
 * Este archivo define la clase principal Application, responsable de orquestar el ciclo de vida de la aplicación,
 * gestionar el contenedor de dependencias, el enrutador, el middleware global y el manejo de errores y excepciones.
 * Implementa el patrón Front Controller y el patrón de Inyección de Dependencias.
 *
 * Consideraciones de seguridad: Maneja excepciones y errores de forma centralizada, permitiendo respuestas seguras.
 * Consideraciones de rendimiento: Inicialización perezosa y ejecución eficiente del middleware.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.1.0
 * @since      1.0.0
 *
 * @see Container
 * @see Router
 * @see MiddlewareInterface
 * @see Request
 * @see Response
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

use Throwable;

/**
 * Clase principal de la aplicación BootZen.
 *
 * Orquesta el ciclo de vida de la aplicación, el registro de servicios, el enrutamiento y el manejo de errores.
 * Permite la integración de middleware global y la extensión mediante el contenedor de dependencias.
 *
 */
class Application
{
    /**
     * Contenedor de dependencias de la aplicación
     * @var Container
     */
    private Container $container;

    /**
     * Enrutador principal de la aplicación
     * @var Router
     */
    private Router $router;

    /**
     * Middleware global registrado para todas las rutas
     * @var array<int, string|MiddlewareInterface>
     */
    private array $globalMiddleware = [];

    /**
     * Indica si la aplicación ya fue inicializada (booted)
     * @var bool
     */
    private bool $booted = false;

    /**
     * Constructor de Application
     *
     * @param Container|null $container Contenedor de dependencias (opcional)
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container ?? new Container();
        $this->router = new Router($this->container);

        $this->registerCoreBindings();
    }

    /**
     * Registra instancias esenciales en el contenedor para inyección de dependencias.
     */
    private function registerCoreBindings(): void
    {
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Application::class, $this);
        $this->container->instance(Router::class, $this->router);
    }

    /**
     * Boot the application
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Register error handler
        $this->registerErrorHandler();

        $this->booted = true;
    }

    /**
     * Se encarga de iniciar la aplicación
     *
     * @param Request|null $request Solicitud HTTP (opcional, por defecto se crea desde globals)
     * @return Response Respuesta HTTP generada
     */
    public function run(?Request $request = null): Response
    {
        $this->boot();

        $request = $request ?? Request::fromGlobals();

        try {
            // Ejecuta el middleware global y luego resuelve la ruta
            return $this->executeGlobalMiddleware($request, function ($request) {
                return $this->router->resolve($request);
            });
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Envia la respuesta HTTP al cliente
     */
    public function send(Response $response): void
    {
        $response->send();
    }

    /**
     * Maneja la solicitud y envía la respuesta al cliente.
     *
     * @param Request|null $request Solicitud HTTP (opcional, por defecto se crea desde globals)
     * @return void
     */
    public function handle(?Request $request = null): void
    {
        $response = $this->run($request);
        $this->send($response);
    }

    /**
     * Agrega un middleware que se ejecutará en todas las solicitudes.
     */
    public function addGlobalMiddleware(string|MiddlewareInterface $middleware): self
    {
        $this->globalMiddleware[] = $middleware;

        return $this;
    }

    /**
     * Ejecuta la cadena de middleware global de forma recursiva.
     * Encadenando cada middleware y finalmente llamando al controlador de la ruta.
     *
     * @param Request $request Solicitud HTTP
     * @param callable $final Función final para ejecutar si no hay más middleware
     * @param array<int, string|MiddlewareInterface>|null $middleware Middleware restante en la cadena (usado internamente)
     * @return Response Respuesta HTTP generada
     */
    private function executeGlobalMiddleware(Request $request, callable $final, ?array $middleware = null): Response
    {
        $middleware = $middleware ?? $this->globalMiddleware;

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

        return $middlewareInstance->process($request, function ($request) use ($middleware, $final) {
            return $this->executeGlobalMiddleware($request, $final, $middleware);
        });
    }

    /**
     * Registra manejadores globales para errores y excepciones no capturadas.
     */
    private function registerErrorHandler(): void
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (! (error_reporting() & $severity)) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e) {
            $this->handleException($e)->send();
        });
    }

    /**
     * Maneja excepciones no capturadas, registrándolas y generando una respuesta adecuada.
     *
     * @param Throwable $e Excepción capturada
     * @param Request|null $request Solicitud HTTP (opcional)
     * @return Response Respuesta HTTP adecuada
     */
    private function handleException(Throwable $e, ?Request $request = null): Response
    {
        // Mostrar detalles solo en modo debug
        error_log(sprintf(
            "Uncaught %s: %s in %s:%d\nStack trace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        // Retorna la respuesta adecuada según el tipo de solicitud
        if ($request && $request->isJson()) {
            return Response::json([
                'error' => 'Internal Server Error',
                'message' => $this->isDebugMode() ? $e->getMessage() : 'Something went wrong',
                'code' => $e->getCode(),
            ], 500);
        }

        $message = $this->isDebugMode()
            ? sprintf(
                '<h1>%s</h1><p>%s</p><pre>%s</pre>',
                get_class($e),
                htmlspecialchars($e->getMessage()),
                htmlspecialchars($e->getTraceAsString())
            )
            : '<h1>500 - Internal Server Error</h1><p>Something went wrong.</p>';

        return Response::html($message, 500);
    }

    /**
     * Verifica si el modo de depuración está habilitado mediante la variable de entorno APP_DEBUG.
     */
    private function isDebugMode(): bool
    {
        return (bool) ($_ENV['APP_DEBUG'] ?? false);
    }

    /**
     * Obtiene la instancia del contenedor de dependencias
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Obtiene la instancia del enrutador
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Registra un servicio en el contenedor de dependencias.
     */
    public function bind(string $abstract, mixed $concrete = null, bool $singleton = false): void
    {
        $this->container->bind($abstract, $concrete, $singleton);
    }

    /**
     * Registra un singleton en el contenedor
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    /**
     * Registra una instancia en el contenedor
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    /**
     * Resuelve un servicio desde el contenedor
     */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    /**
     * Registra rutas usando un callback que recibe el enrutador.
     */
    public function routes(callable $callback): void
    {
        $callback($this->router);
    }

    /**
     * Método mágico para enrutar llamadas al enrutador
     *
     * @param string $method
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists($this->router, $method)) {
            return $this->router->$method(...$arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on Application or Router");
    }
}
