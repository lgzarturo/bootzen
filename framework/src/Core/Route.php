<?php

/**
 * Route.php
 *
 * Define una ruta HTTP: métodos, patrón, handler, middleware, nombre y restricciones de parámetros.
 * El patrón se compila a una expresión regular una sola vez, la primera vez que se necesita.
 *
 * Sintaxis de parámetros:
 * - `{id}`: cualquier segmento sin barra.
 * - `{id:\d+}`: segmento que cumple la expresión regular indicada.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      1.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use InvalidArgumentException;

/**
 * Ruta registrada en el Router.
 *
 * @example $router->get('/tareas/{id:\d+}', [TareaController::class, 'show'])->name('tareas.show');
 */
class Route
{
    /**
     * Captura {nombre} y {nombre:regex}; la regex puede incluir cuantificadores como \d{4}.
     */
    private const PLACEHOLDER = '/\{([A-Za-z_][A-Za-z0-9_]*)(?::((?:[^{}]|\{[^{}]*\})+))?\}/';

    /**
     * Métodos HTTP aceptados (en mayúsculas).
     *
     * @var list<string>
     */
    private array $methods;

    private ?string $name = null;

    /**
     * Restricciones regex por parámetro.
     *
     * @var array<string, string>
     */
    private array $constraints = [];

    /**
     * Expresión regular compilada (null hasta el primer uso).
     */
    private ?string $regex = null;

    /**
     * @param list<string> $methods Métodos HTTP
     * @param string $path Patrón de la ruta, normalizado por el Router
     * @param mixed $handler Closure, [Clase::class, 'metodo'] o clase invocable
     * @param list<string|MiddlewareInterface> $middleware Middleware de la ruta
     * @param string $namePrefix Prefijo de nombre heredado de los grupos ('as')
     */
    public function __construct(
        array $methods,
        private readonly string $path,
        private readonly mixed $handler,
        private array $middleware = [],
        private readonly string $namePrefix = ''
    ) {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
    }

    /**
     * Métodos HTTP aceptados.
     *
     * @return list<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Indica si la ruta acepta el método. HEAD se atiende con las rutas GET.
     */
    public function allowsMethod(string $method): bool
    {
        $method = strtoupper($method);

        return in_array($method, $this->methods, true)
            || ($method === 'HEAD' && in_array('GET', $this->methods, true));
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHandler(): mixed
    {
        return $this->handler;
    }

    /**
     * @return list<string|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Asigna el nombre de la ruta (se le antepone el prefijo 'as' de los grupos).
     */
    public function name(string $name): self
    {
        $this->name = $this->namePrefix . $name;

        return $this;
    }

    /**
     * Agrega middleware a la ruta (clase, alias o grupo).
     *
     * @param string|MiddlewareInterface|list<string|MiddlewareInterface> $middleware
     */
    public function middleware(string|MiddlewareInterface|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, is_array($middleware) ? $middleware : [$middleware]);

        return $this;
    }

    /**
     * Restringe uno o varios parámetros con expresiones regulares.
     *
     * @param string|array<string, string> $parameter Nombre del parámetro o mapa nombre => regex
     * @param string|null $pattern Regex sin delimitadores, si $parameter es un nombre
     * @example $route->where('id', '\d+');
     * @example $route->where(['anio' => '\d{4}', 'slug' => '[a-z0-9-]+']);
     */
    public function where(string|array $parameter, ?string $pattern = null): self
    {
        $constraints = is_array($parameter) ? $parameter : [$parameter => (string) $pattern];

        foreach ($constraints as $name => $regex) {
            $this->constraints[$name] = $regex;
        }

        $this->regex = null;

        return $this;
    }

    /**
     * Nombres de los parámetros del patrón, en orden.
     *
     * @return list<string>
     */
    public function getParameterNames(): array
    {
        preg_match_all(self::PLACEHOLDER, $this->path, $matches);

        return $matches[1];
    }

    /**
     * Compara la ruta con un path y devuelve sus parámetros, o null si no coincide.
     *
     * @param string $path Path normalizado de la petición
     * @return array<string, string>|null
     * @throws InvalidArgumentException Si alguna restricción no es una regex válida
     */
    public function matches(string $path): ?array
    {
        set_error_handler(static fn (): bool => true);

        try {
            $result = preg_match($this->compile(), $path, $matches);
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            throw new InvalidArgumentException("Patrón inválido en la ruta [{$this->path}]: revisa sus restricciones.");
        }

        if ($result !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = rawurldecode($value);
            }
        }

        return $parameters;
    }

    /**
     * Genera el path de la ruta con los parámetros dados. Los sobrantes se agregan como query string.
     *
     * @param array<string, string|int|float|bool|\Stringable> $parameters
     * @throws InvalidArgumentException Si falta un parámetro o no cumple su restricción
     */
    public function url(array $parameters = []): string
    {
        $path = (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $match) use (&$parameters): string {
                $name = $match[1];

                if (! array_key_exists($name, $parameters)) {
                    throw new InvalidArgumentException(
                        "Falta el parámetro [{$name}] para generar la URL de la ruta [{$this->describe()}]."
                    );
                }

                $value = $parameters[$name];
                $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                unset($parameters[$name]);

                $constraint = $this->constraints[$name] ?? ($match[2] ?? '[^/]+');
                if (preg_match('#^(?:' . $constraint . ')$#u', $value) !== 1) {
                    throw new InvalidArgumentException(
                        "El valor [{$value}] no cumple la restricción del parámetro [{$name}] en [{$this->describe()}]."
                    );
                }

                return rawurlencode($value);
            },
            $this->path
        );

        return $parameters === [] ? $path : $path . '?' . http_build_query($parameters);
    }

    /**
     * Compila el patrón a regex con grupos nombrados.
     */
    private function compile(): string
    {
        if ($this->regex !== null) {
            return $this->regex;
        }

        preg_match_all(self::PLACEHOLDER, $this->path, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $regex = '';
        $offset = 0;

        foreach ($matches as $match) {
            [$placeholder, $position] = $match[0];
            $name = $match[1][0];
            $inline = isset($match[2]) && $match[2][1] !== -1 ? $match[2][0] : '[^/]+';

            $regex .= preg_quote(substr($this->path, $offset, $position - $offset), '#');
            $regex .= '(?P<' . $name . '>' . ($this->constraints[$name] ?? $inline) . ')';
            $offset = $position + strlen($placeholder);
        }

        $regex .= preg_quote(substr($this->path, $offset), '#');

        return $this->regex = '#^' . $regex . '$#u';
    }

    private function describe(): string
    {
        return $this->name ?? $this->path;
    }
}
