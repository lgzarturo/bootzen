<?php

/**
 * Request.php
 *
 * Representa y encapsula una solicitud HTTP en el framework BootZen.
 * Permite acceder de forma segura y tipada a los datos de la petición, headers, cuerpo, archivos, cookies y servidor.
 * Facilita la validación, sanitización y manipulación de la entrada del usuario, siguiendo el patrón Value Object.
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

use BootZen\Session\Session;
use BootZen\Validation\ValidationException;
use BootZen\Validation\Validator;
use LogicException;

/**
 * Clase Request
 *
 * Encapsula todos los datos relevantes de una solicitud HTTP, permitiendo su acceso y manipulación de forma segura.
 * Implementa el patrón Value Object y es utilizada por controladores, middlewares y servicios del framework BootZen.
 *
 * Consideraciones de seguridad: sanitiza y valida la entrada del usuario, protege contra ataques comunes (XSS, CSRF).
 * Consideraciones de rendimiento: accede a los datos de la petición de forma eficiente y perezosa.
 *
 */
class Request
{
    /**
     * Método HTTP de la solicitud (GET, POST, etc.)
     *
     * @var string
     */
    private string $method;

    /**
     * URI completa de la solicitud
     *
     * @var string
     */
    private string $uri;

    /**
     * Ruta (path) de la solicitud
     *
     * @var string
     */
    private string $path;

    /**
     * Parámetros de consulta (query string)
     *
     * @var array<string, mixed>
     */
    private array $query;

    /**
     * Cabeceras HTTP de la solicitud
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Datos del cuerpo de la solicitud (POST, PUT, etc.)
     *
     * @var array<string, mixed>
     */
    private array $body;

    /**
     * Archivos subidos en la solicitud
     *
     * @var array<string, array<string, mixed>>
     */
    private array $files;

    /**
     * Variables del servidor asociadas a la solicitud
     *
     * @var array<string, mixed>
     */
    private array $server;

    /**
     * Cookies enviadas en la solicitud
     *
     * @var array<string, mixed>
     */
    private array $cookies;

    /**
     * Atributos de la petición; el Router guarda aquí los parámetros de ruta
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Ruta que resolvió la petición (null antes del enrutamiento)
     */
    private ?Route $route = null;

    /**
     * Sesión de la petición (la asigna el middleware StartSession).
     */
    private ?Session $session = null;

    /**
     * Métodos que un formulario puede simular con el campo _method
     */
    private const SPOOFABLE_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * Constructor de Request
     *
     * Un POST con el campo `_method` (o la cabecera X-HTTP-Method-Override) igual a PUT, PATCH o DELETE
     * se trata como ese método, para que los formularios HTML puedan usar esas rutas.
     *
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $uri URI completa de la solicitud
     * @param array<string, string> $headers Cabeceras HTTP
     * @param array<string, mixed> $body Datos del cuerpo de la solicitud (POST, PUT, etc.)
     * @param array<string, array<string, mixed>> $files Archivos subidos
     * @param array<string, mixed> $server Variables del servidor ($_SERVER)
     * @param array<string, mixed> $cookies Cookies de la solicitud
     * @example
     *   $request = new Request('POST', '/api/user', ['Content-Type' => 'application/json'], ['name' => 'Juan']);
     */
    public function __construct(
        string $method,
        string $uri,
        array $headers = [],
        array $body = [],
        array $files = [],
        array $server = [],
        array $cookies = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        // '//ruta' se interpretaría como host; se colapsan las barras iniciales antes de analizar
        $parsedPath = parse_url((string) preg_replace('#^/{2,}#', '/', $uri), PHP_URL_PATH);
        $this->path = is_string($parsedPath) ? $parsedPath : '/';
        $this->query = [];

        if ($queryString = parse_url((string) preg_replace('#^/{2,}#', '/', $uri), PHP_URL_QUERY)) {
            $parsedQuery = [];
            parse_str($queryString, $parsedQuery);
            // Asegura que las claves sean strings
            $this->query = [];
            foreach ($parsedQuery as $k => $v) {
                if (is_string($k)) {
                    $this->query[$k] = $v;
                }
            }
        }

        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;

        if ($this->method === 'POST') {
            $override = $body['_method'] ?? $this->getHeader('x-http-method-override');
            if (is_string($override) && in_array(strtoupper($override), self::SPOOFABLE_METHODS, true)) {
                $this->method = strtoupper($override);
            }
        }
    }

    /**
     * Crea una instancia de Request a partir de las variables globales PHP.
     *
     * @param string|null $rawBody Cuerpo crudo de la petición; por defecto se lee php://input
     * @return self Instancia de Request
     * @example $request = Request::fromGlobals();
     */
    public static function fromGlobals(?string $rawBody = null): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Obtener headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }

        // CONTENT_TYPE y CONTENT_LENGTH llegan en $_SERVER sin el prefijo HTTP_
        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $header) {
            if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
                $headers[$header] = $_SERVER[$key];
            }
        }

        // Obtener body según content-type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (is_string($contentType) && str_contains($contentType, 'application/json')) {
            $input = $rawBody ?? file_get_contents('php://input');
            $decoded = json_decode($input === false ? '' : $input, true);
            $body = is_array($decoded) ? $decoded : [];
        } else {
            $body = $_POST;
        }

        return new self(
            $method,
            $uri,
            $headers,
            $body,
            $_FILES,
            $_SERVER,
            $_COOKIE
        );
    }

    /**
     * Obtiene el método HTTP de la solicitud
     *
     * @return string Método HTTP
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Obtiene la URI completa de la solicitud
     *
     * @return string URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Obtiene la ruta (path) de la solicitud
     *
     * @return string Ruta
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Obtiene los parámetros de consulta de la solicitud
     *
     * @param string|null $key Clave del parámetro (opcional)
     * @param mixed $default Valor por defecto si no se encuentra el parámetro
     * @return mixed Valor del parámetro o array completo
     * @example $id = $request->getQuery('id');
     */
    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Obtiene el valor de una cabecera HTTP
     *
     * @param string $name Nombre de la cabecera
     * @return string|null Valor de la cabecera o null si no existe
     * @example $token = $request->getHeader('Authorization');
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Obtiene todas las cabeceras de la solicitud
     *
     * @return array<string, string> Array de cabeceras
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Obtiene los datos del cuerpo de la solicitud
     *
     * @param string|null $key Clave del dato en el cuerpo (opcional)
     * @param mixed $default Valor por defecto si no se encuentra el dato
     * @return mixed Valor del dato o array completo
     * @example $name = $request->getBody('name');
     */
    public function getBody(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Obtiene un archivo subido por la solicitud
     *
     * @param string $key Clave del archivo en el array $_FILES
     * @return array<string, mixed>|null Información del archivo o null si no existe
     * @example $file = $request->getFile('avatar');
     */
    public function getFile(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Archivo subido como objeto (tipo detectado, store(), storePublic()), o null si no llegó.
     *
     * @example $ruta = $request->file('foto')?->storePublic('avatares');
     */
    public function file(string $key): ?UploadedFile
    {
        return UploadedFile::fromArray($this->files[$key] ?? null);
    }

    /**
     * Obtiene todos los archivos subidos por la solicitud
     *
     * @return array<string, array<string, mixed>> Array de archivos
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Obtiene las variables del servidor
     *
     * @param string|null $key Clave de la variable del servidor (opcional)
     * @param mixed $default Valor por defecto si no se encuentra la variable
     * @return mixed Valor de la variable o array completo
     * @example $host = $request->getServer('HTTP_HOST');
     */
    public function getServer(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    /**
     * Obtiene el valor de una cookie
     *
     * @param string $name Nombre de la cookie
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor de la cookie o valor por defecto
     * @example $token = $request->getCookie('session_token');
     */
    public function getCookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * Devuelve una copia con atributos agregados (los parámetros de ruta, por ejemplo).
     *
     * @param array<string, mixed> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        $clone = clone $this;
        $clone->attributes = array_merge($this->attributes, $attributes);

        return $clone;
    }

    /**
     * Devuelve una copia asociada a la ruta que la resolvió.
     */
    public function withRoute(Route $route): self
    {
        $clone = clone $this;
        $clone->route = $route;

        return $clone;
    }

    /**
     * Devuelve una copia con la sesión asignada.
     *
     * @internal Lo usa StartSession
     */
    public function withSession(Session $session): self
    {
        $clone = clone $this;
        $clone->session = $session;

        return $clone;
    }

    /**
     * Sesión de la petición.
     *
     * @throws LogicException Si la ruta no pasa por el middleware StartSession (grupo web)
     */
    public function session(): Session
    {
        return $this->session ?? throw new LogicException(
            'Esta petición no tiene sesión: la ruta necesita el middleware StartSession (grupo web).'
        );
    }

    public function hasSession(): bool
    {
        return $this->session !== null;
    }

    /**
     * Valida la entrada (cuerpo, query string y archivos) y devuelve solo los campos validados.
     * Si falla lanza ValidationException: en formularios vuelve a la página anterior con los errores
     * y el input; en peticiones JSON responde 422 con los errores.
     *
     * @param array<string, string|list<string|\Closure>> $rules Campo => reglas ('required|email' o lista)
     * @param array<string, string> $messages Mensajes propios: 'campo.regla' o 'regla' => mensaje
     * @param array<string, string> $attributes Nombres legibles de los campos
     * @return array<string, mixed>
     * @throws ValidationException
     * @example $datos = $request->validate(['titulo' => 'required|string|max:120']);
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return Validator::make(array_replace($this->all(), $this->files), $rules, $messages, $attributes)->validate();
    }

    /**
     * Ruta que resolvió la petición, o null si aún no se enruta.
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * Obtiene un parámetro de ruta.
     *
     * @example $request->param('id'); // para /tareas/{id}
     */
    public function param(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Todos los parámetros de ruta.
     *
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return $this->attributes;
    }

    /**
     * Obtiene un dato de entrada: cuerpo o query string (el cuerpo tiene prioridad).
     * Acepta notación de puntos para arrays anidados: 'items.0.precio'.
     *
     * @param string|null $key Clave o null para todos los datos
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = $this->all();

        if ($key === null) {
            return $input;
        }

        $value = $input;
        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Todos los datos de entrada (query string + cuerpo), sin el campo interno _method.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $input = array_replace($this->query, $this->body);
        unset($input['_method']);

        return $input;
    }

    /**
     * Solo las claves indicadas que estén presentes.
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Todos los datos de entrada excepto las claves indicadas.
     *
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Indica si la clave está presente en la entrada (aunque esté vacía).
     */
    public function has(string $key): bool
    {
        $missing = new \stdClass();

        return $this->input($key, $missing) !== $missing;
    }

    /**
     * Indica si la clave está presente y no está vacía.
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return is_array($value) ? $value !== [] : trim((string) (is_scalar($value) ? $value : '')) !== '';
    }

    /**
     * Interpreta un dato como booleano ('1', 'true', 'on', 'yes' son true).
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Interpreta un dato como entero; devuelve el valor por defecto si no es numérico.
     */
    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Indica si el cliente espera una respuesta JSON (Accept o Content-Type JSON).
     */
    public function wantsJson(): bool
    {
        $accept = strtolower($this->getHeader('accept') ?? '');

        return str_contains($accept, '/json') || str_contains($accept, '+json') || $this->isJson();
    }

    /**
     * Determina si la solicitud es de tipo JSON
     *
     * @return bool true si es JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type') ?? '';

        return str_contains($contentType, 'application/json');
    }

    /**
     * Determina si la solicitud es AJAX
     *
     * @return bool true si es AJAX
     */
    public function isAjax(): bool
    {
        return strtolower($this->getHeader('x-requested-with') ?? '') === 'xmlhttprequest';
    }

    /**
     * Determina si la solicitud es segura (HTTPS)
     *
     * @return bool true si es HTTPS
     */
    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? 'off') !== 'off' ||
               ($this->server['SERVER_PORT'] ?? 80) == 443 ||
               strtolower($this->getHeader('x-forwarded-proto') ?? '') === 'https';
    }

    /**
     * Obtiene la IP del cliente que realiza la solicitud
     *
     * @return string IP del cliente
     * @example $ip = $request->getClientIp();
     */
    public function getClientIp(): string
    {
        $remote = $this->server['REMOTE_ADDR'] ?? null;

        // Si el cliente se conecta directo (IP pública), las cabeceras de proxy no son confiables:
        // cualquiera puede enviarlas para hacerse pasar por otra IP y saltarse los límites de peticiones
        if (is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $remote;
        }

        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            if (! empty($this->server[$key])) {
                $ip = trim(explode(',', $this->server[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
