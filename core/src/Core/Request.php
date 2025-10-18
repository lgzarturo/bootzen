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
 * @version    1.0.5
 * @since      1.0.0
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

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
     * Constructor de Request
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
        $parsedPath = parse_url($uri, PHP_URL_PATH);
        $this->path = is_string($parsedPath) ? $parsedPath : '/';
        $this->query = [];

        if ($queryString = parse_url($uri, PHP_URL_QUERY)) {
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
    }

    /**
     * Crea una instancia de Request a partir de las variables globales PHP.
     *
     * @return self Instancia de Request
     * @example $request = Request::fromGlobals();
     */
    public static function fromGlobals(): self
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

        // Obtener body según content-type
        $body = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            $body = json_decode($input === false ? '' : $input, true) ?? [];
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
