<?php

/**
 * Núcleo de la aplicación BootZen
 *
 * Define la clase Application, responsable del ciclo de vida: carga del entorno (.env) y la
 * configuración (config/), registro de servicios en el contenedor, middleware global, enrutamiento
 * y manejo centralizado de errores.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      1.0.0
 *
 * @see Container
 * @see Router
 * @see Pipeline
 * @see ExceptionHandler
 */

declare(strict_types=1);

namespace BootZen\Core;

use BootZen\Auth\Auth;
use BootZen\Auth\PasswordReset;
use BootZen\Database\Connection;
use BootZen\Database\Migrator;
use BootZen\Database\Model;
use BootZen\Database\SeederRunner;
use BootZen\Mail\ArrayMailer;
use BootZen\Mail\LogMailer;
use BootZen\Mail\Mailer;
use BootZen\Session\ArraySessionStore;
use BootZen\Session\FileSessionStore;
use BootZen\Session\Session;
use BootZen\Session\SessionStore;
use BootZen\Validation\Validator;
use BootZen\View\View;
use ErrorException;
use LogicException;
use Throwable;

/**
 * Clase principal de la aplicación BootZen.
 *
 * Al construirse carga .env y config/, ajusta la zona horaria y registra en el contenedor:
 * Application, Container, Router, Pipeline, Config, Logger, Cache, View, ExceptionHandler, Connection
 * (perezosa), Migrator, SeederRunner, SessionStore, Auth, PasswordReset y Mailer.
 *
 * @example
 *   $app = new Application(dirname(__DIR__));
 *   $app->routes(require __DIR__ . '/../routes/web.php');
 *   $app->handle();
 */
class Application
{
    /**
     * Última instancia creada; la usan los helpers globales (config(), route(), logger()...).
     */
    private static ?self $instance = null;

    private readonly string $basePath;

    private readonly Container $container;

    private readonly Router $router;

    private readonly Config $config;

    /**
     * Middleware global registrado para todas las rutas
     *
     * @var list<string|MiddlewareInterface>
     */
    private array $globalMiddleware = [];

    /**
     * @param string|null $basePath Raíz del proyecto; por defecto BOOTZEN_BASE_PATH
     * @param Container|null $container Contenedor de dependencias (opcional)
     */
    public function __construct(?string $basePath = null, ?Container $container = null)
    {
        $this->basePath = rtrim($basePath ?? self::defaultBasePath(), '/');
        $this->container = $container ?? new Container();
        $this->router = new Router($this->container);

        Env::load($this->basePath('.env'));
        // En tests se ignora la caché de config: phpunit.xml cambia variables (base en memoria, sesión array)
        $this->config = env('APP_ENV') === 'testing'
            ? Config::fromDirectory($this->basePath('config'))
            : Config::load($this->basePath('config'), $this->basePath('storage/cache/config.php'));
        $this->configureTimezone();
        $this->registerCoreBindings();

        self::$instance = $this;
    }

    /**
     * Devuelve la instancia actual de la aplicación.
     *
     * @throws LogicException Si todavía no se creó ninguna Application
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new LogicException('No hay una Application creada. Crea una en bootstrap/app.php.');
        }

        return self::$instance;
    }

    /**
     * Indica si ya existe una instancia de la aplicación.
     */
    public static function hasInstance(): bool
    {
        return self::$instance !== null;
    }

    /**
     * Ruta absoluta dentro del proyecto.
     *
     * @example $app->basePath('storage/logs');
     */
    public function basePath(string $path = ''): string
    {
        return $path === '' ? $this->basePath : $this->basePath . '/' . ltrim($path, '/');
    }

    /**
     * Repositorio de configuración.
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Indica si el modo depuración está activo (config app.debug, o APP_DEBUG si no hay config).
     */
    public function isDebug(): bool
    {
        $debug = $this->config->get('app.debug', env('APP_DEBUG', false));

        return filter_var($debug, FILTER_VALIDATE_BOOL);
    }

    /**
     * Procesa una petición y devuelve la respuesta, sin enviarla ni tocar el estado global de PHP.
     * Es lo que usan los tests.
     *
     * @param Request|null $request Solicitud HTTP (por defecto se crea desde las variables globales)
     */
    public function run(?Request $request = null): Response
    {
        $request ??= Request::fromGlobals();

        try {
            return $this->router->getPipeline()->run(
                $this->globalMiddleware,
                $request,
                fn (Request $request): Response => $this->router->resolve($request)
            );
        } catch (Throwable $e) {
            $handler = $this->exceptionHandler();
            $handler->report($e, $request);

            return $handler->render($e, $request);
        }
    }

    /**
     * Atiende la petición real: registra los manejadores globales de errores, procesa y envía.
     *
     * @param Request|null $request Solicitud HTTP (por defecto se crea desde las variables globales)
     */
    public function handle(?Request $request = null): void
    {
        $this->registerErrorHandlers();

        $this->run($request)->send();
    }

    /**
     * Agrega middleware que se ejecuta en todas las peticiones (clase, instancia, alias o grupo).
     */
    public function addGlobalMiddleware(string|MiddlewareInterface $middleware): self
    {
        $this->globalMiddleware[] = $middleware;

        return $this;
    }

    /**
     * Registra un alias de middleware para usarlo en rutas y grupos.
     *
     * @example $app->aliasMiddleware('cors', CorsMiddleware::class);
     */
    public function aliasMiddleware(string $name, string $class): self
    {
        $this->router->aliasMiddleware($name, $class);

        return $this;
    }

    /**
     * Registra un grupo de middleware.
     *
     * @param list<string|MiddlewareInterface> $middleware
     * @example $app->middlewareGroup('api', ['cors']);
     */
    public function middlewareGroup(string $name, array $middleware): self
    {
        $this->router->middlewareGroup($name, $middleware);

        return $this;
    }

    /**
     * Registra rutas usando un callback que recibe el enrutador.
     *
     * @param callable(Router): void $callback
     */
    public function routes(callable $callback): void
    {
        $callback($this->router);
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
     * Registra en el contenedor los servicios del framework.
     */
    private function registerCoreBindings(): void
    {
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Pipeline::class, $this->router->getPipeline());
        $this->container->instance(Config::class, $this->config);

        $this->container->singleton(Connection::class, fn (): Connection => Connection::fromConfig(
            $this->databaseConfig(),
            $this->basePath
        ));
        $this->container->bind(Migrator::class, fn (Container $container): Migrator => new Migrator(
            $this->connectionFrom($container),
            $this->basePath('database/migrations')
        ));
        $this->container->bind(SeederRunner::class, fn (Container $container): SeederRunner => new SeederRunner(
            $this->connectionFrom($container),
            $this->basePath('database/seeds')
        ));

        // Los modelos piden la conexión solo cuando se usan: sin consultas no se abre la base de datos
        Model::setConnectionResolver(fn (): Connection => $this->connectionFrom($this->container));
        Validator::setConnectionResolver(fn (): Connection => $this->connectionFrom($this->container));

        $this->container->singleton(Logger::class, fn (): Logger => new Logger(
            $this->basePath('storage/logs'),
            (string) $this->config->get('app.log_level', 'debug')
        ));

        $this->container->singleton(Cache::class, fn (): Cache => new Cache(
            $this->config->get('cache.driver', 'file') === 'array' ? null : $this->basePath('storage/cache/data')
        ));

        $this->container->singleton(SessionStore::class, fn (): SessionStore => match ($this->config->get('session.driver', 'file')) {
            'array' => new ArraySessionStore(),
            'file' => new FileSessionStore($this->basePath('storage/sessions'), $this->sessionLifetime()),
            default => throw new LogicException('Driver de sesión no soportado. Usa file o array en config/session.php.'),
        });

        $this->container->singleton(Auth::class, function (Container $container): Auth {
            $model = $this->config->get('auth.model', 'App\\Models\\User');

            return new Auth(static function () use ($container): ?Session {
                $session = $container->bound(Session::class) ? $container->make(Session::class) : null;

                return $session instanceof Session ? $session : null;
            }, is_string($model) ? $model : 'App\\Models\\User');
        });

        $this->container->bind(PasswordReset::class, fn (Container $container): PasswordReset => new PasswordReset(
            $this->connectionFrom($container),
            'password_resets',
            (int) $this->config->get('auth.reset_expire', 60)
        ));

        $this->container->singleton(Mailer::class, fn (Container $container): Mailer => match ($this->config->get('mail.driver', 'log')) {
            'array' => new ArrayMailer(),
            'log' => new LogMailer($container->make(Logger::class)),
            default => throw new LogicException('Driver de correo no soportado. Usa log o array en config/mail.php (SMTP llega más adelante).'),
        });

        $this->container->singleton(View::class, fn (Container $container): View => new View(
            $this->basePath('resources/views'),
            $this->basePath('storage/cache/views'),
            $container
        ));

        $this->container->singleton(ExceptionHandler::class, fn (Container $container): ExceptionHandler => new ExceptionHandler(
            $container->make(Logger::class),
            $container->make(View::class),
            $this->isDebug()
        ));
    }

    /**
     * Configuración de la conexión por defecto (config/database.php). Sin archivo, SQLite en database/app.sqlite.
     *
     * @return array<string, mixed>
     * @throws LogicException Si la conexión por defecto no está definida
     */
    private function databaseConfig(): array
    {
        $default = $this->config->get('database.default', 'sqlite');
        $connections = $this->config->get('database.connections', [
            'sqlite' => ['driver' => 'sqlite', 'database' => 'database/app.sqlite'],
        ]);
        $config = is_array($connections) && is_string($default) ? ($connections[$default] ?? null) : null;

        if (! is_array($config)) {
            throw new LogicException('La conexión [' . (is_string($default) ? $default : '?') . '] no está definida en config/database.php.');
        }

        /** @var array<string, mixed> $config */
        return $config;
    }

    /**
     * Duración de las sesiones en segundos (config session.lifetime está en minutos).
     */
    private function sessionLifetime(): int
    {
        $minutes = $this->config->get('session.lifetime', 120);

        return (is_numeric($minutes) ? (int) $minutes : 120) * 60;
    }

    private function connectionFrom(Container $container): Connection
    {
        $connection = $container->make(Connection::class);

        if (! $connection instanceof Connection) {
            throw new LogicException('El servicio Connection debe ser una instancia de ' . Connection::class . '.');
        }

        return $connection;
    }

    private function exceptionHandler(): ExceptionHandler
    {
        $handler = $this->container->make(ExceptionHandler::class);

        if (! $handler instanceof ExceptionHandler) {
            throw new LogicException('El servicio ExceptionHandler debe extender ' . ExceptionHandler::class . '.');
        }

        return $handler;
    }

    /**
     * Ajusta la zona horaria según config app.timezone.
     */
    private function configureTimezone(): void
    {
        $timezone = $this->config->get('app.timezone');

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
        }
    }

    /**
     * Registra manejadores globales: warnings como excepciones, excepciones no capturadas y errores fatales.
     */
    private function registerErrorHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (! (error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            $handler = $this->exceptionHandler();
            $handler->report($e);
            $handler->render($e)->send();
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

            if ($error === null || ($error['type'] & $fatal) === 0) {
                return;
            }

            $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            $handler = $this->exceptionHandler();
            $handler->report($e);

            if (! headers_sent()) {
                $handler->render($e)->send();
            }
        });
    }

    private static function defaultBasePath(): string
    {
        if (defined('BOOTZEN_BASE_PATH')) {
            return (string) constant('BOOTZEN_BASE_PATH');
        }

        return (string) getcwd();
    }
}
