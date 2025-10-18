<?php

/**
 * Response.php
 *
 * Representa y construye una respuesta HTTP en el framework BootZen.
 * Permite definir el código de estado, cabeceras, cuerpo, cookies y enviar la respuesta al cliente.
 * Facilita la generación de respuestas JSON, HTML, redirecciones y errores estándar, siguiendo el patrón Value Object.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.7
 * @since      1.0.0
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

/**
 * Clase Response
 *
 * Encapsula todos los datos relevantes de una respuesta HTTP, permitiendo su construcción y envío de forma segura.
 * Implementa el patrón Value Object y es utilizada por controladores, middlewares y servicios del framework BootZen.
 *
 * Consideraciones de seguridad: permite definir cabeceras y cookies seguras, previniendo ataques de tipo
 * header injection.
 * Consideraciones de rendimiento: permite respuestas inmutables y reutilizables, optimizando el flujo HTTP.
 *
 */
class Response
{
    /**
     * Código de estado HTTP de la respuesta
     *
     * @var int
     */
    private int $statusCode;

    /**
     * Cabeceras HTTP de la respuesta
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * Cuerpo de la respuesta
     *
     * @var string
     */
    private string $body;

    /**
     * Cookies a enviar con la respuesta
     *
     * @var array<string, array<string, mixed>>
     */
    private array $cookies;

    /**
     * Mapa de códigos de estado HTTP a textos descriptivos
     *
     * @var array<int, string>
     */
    private static array $statusTexts = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * Constructor de Response
     *
     * @param int $statusCode Código de estado HTTP (por defecto 200)
     * @param string $body Cuerpo de la respuesta (por defecto vacío)
     * @param array<string, string> $headers Cabeceras HTTP (por defecto vacío)
     * @example $response = new Response(200, 'OK', ['Content-Type' => 'text/plain']);
     */
    public function __construct(int $statusCode = 200, string $body = '', array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->cookies = [];
    }

    /**
     * Crea una respuesta JSON
     *
     * @param array<string, mixed> $data Datos a codificar en JSON
     * @param int $statusCode Código de estado HTTP (por defecto 200)
     * @param array<string, string> $headers Cabeceras HTTP adicionales
     * @return self Nueva instancia de Response con contenido JSON
     * @throws \JsonException Si la codificación JSON falla
     * @example $response = Response::json(['ok' => true]);
     */
    public static function json(array $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['content-type'] = 'application/json';

        return new self(
            $statusCode,
            json_encode($data, JSON_THROW_ON_ERROR),
            $headers
        );
    }

    /**
     * Crea una respuesta HTML
     *
     * @param string $html Contenido HTML
     * @param int $statusCode Código de estado HTTP (por defecto 200)
     * @param array<string, string> $headers Cabeceras HTTP adicionales
     * @return self Nueva instancia de Response con contenido HTML
     * @example $response = Response::html('<h1>Hola</h1>');
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $headers['content-type'] = 'text/html; charset=utf-8';

        return new self($statusCode, $html, $headers);
    }

    /**
     * Crea una respuesta de redirección HTTP
     *
     * @param string $url URL de destino
     * @param int $statusCode Código de estado HTTP (por defecto 302)
     * @return self Nueva instancia de Response de redirección
     * @example $response = Response::redirect('/login');
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self($statusCode, '', ['location' => $url]);
    }

    /**
     * Crea una respuesta 404 Not Found
     *
     * @param string $message Mensaje de error
     * @return self Nueva instancia de Response 404
     * @example $response = Response::notFound();
     */
    public static function notFound(string $message = 'Not Found'): self
    {
        return new self(404, $message);
    }

    /**
     * Crea una respuesta 500 Internal Server Error
     *
     * @param string $message Mensaje de error
     * @return self Nueva instancia de Response 500
     * @example $response = Response::serverError();
     */
    public static function serverError(string $message = 'Internal Server Error'): self
    {
        return new self(500, $message);
    }

    /**
     * Crea una respuesta 401 Unauthorized
     *
     * @param string $message Mensaje de error
     * @return self Nueva instancia de Response 401
     * @example $response = Response::unauthorized();
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(401, $message);
    }

    /**
     * Crea una respuesta 403 Forbidden
     *
     * @param string $message Mensaje de error
     * @return self Nueva instancia de Response 403
     * @example $response = Response::forbidden();
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    /**
     * Crea una respuesta 400 Bad Request
     *
     * @param string $message Mensaje de error
     * @return self Nueva instancia de Response 400
     * @example $response = Response::badRequest();
     */
    public static function badRequest(string $message = 'Bad Request'): self
    {
        return new self(400, $message);
    }

    /**
     * Crea una respuesta 429 Too Many Requests
     *
     * @param string $message Mensaje de error
     * @return self Nueva instancia de Response 429
     * @example $response = Response::tooManyRequests();
     */
    public static function tooManyRequests(string $message = 'Too Many Requests'): self
    {
        return new self(429, $message);
    }

    /**
     * Obtiene el código de estado HTTP de la respuesta
     *
     * @return int Código de estado
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtiene el texto descriptivo del código de estado HTTP
     *
     * @return string Texto del estado
     */
    public function getStatusText(): string
    {
        return self::$statusTexts[$this->statusCode] ?? 'Unknown Status';
    }

    /**
     * Obtiene el cuerpo de la respuesta
     *
     * @return string Cuerpo de la respuesta
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Obtiene todas las cabeceras de la respuesta
     *
     * @return array<string, string> Array de cabeceras
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Obtiene el valor de una cabecera HTTP
     *
     * @param string $name Nombre de la cabecera
     * @return string|null Valor de la cabecera o null si no existe
     * @example $type = $response->getHeader('Content-Type');
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Devuelve una nueva instancia con el código de estado modificado
     *
     * @param int $statusCode Nuevo código de estado
     * @return self Instancia modificada
     * @example $response = $response->withStatus(404);
     */
    public function withStatus(int $statusCode): self
    {
        $new = clone $this;
        $new->statusCode = $statusCode;

        return $new;
    }

    /**
     * Devuelve una nueva instancia con una cabecera añadida o modificada
     *
     * @param string $name Nombre de la cabecera
     * @param string $value Valor de la cabecera
     * @return self Instancia modificada
     * @example $response = $response->withHeader('X-App', 'BootZen');
     */
    public function withHeader(string $name, string $value): self
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = $value;

        return $new;
    }

    /**
     * Devuelve una nueva instancia con el cuerpo modificado
     *
     * @param string $body Nuevo cuerpo
     * @return self Instancia modificada
     * @example $response = $response->withBody('<h1>Hola</h1>');
     */
    public function withBody(string $body): self
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    /**
     * Devuelve una nueva instancia con una cookie añadida
     *
     * @param string $name Nombre de la cookie
     * @param string $value Valor de la cookie
     * @param int $expires Tiempo de expiración (timestamp)
     * @param string $path Ruta de la cookie
     * @param string $domain Dominio de la cookie
     * @param bool $secure Si la cookie es segura
     * @param bool $httpOnly Si la cookie es solo HTTP
     * @param string $sameSite Política SameSite (Lax, Strict, None)
     * @return self Instancia modificada
     * @example $response = $response->withCookie('token', 'abc123', time()+3600);
     */
    public function withCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $new = clone $this;
        $new->cookies[$name] = [
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];

        return $new;
    }

    /**
     * Envía la respuesta HTTP al cliente (status, headers, cookies y body)
     *
     * @return void
     * @example $response->send();
     */
    public function send(): void
    {
        // Enviar status code
        http_response_code($this->statusCode);

        // Enviar headers
        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        // Enviar cookies
        foreach ($this->cookies as $name => $cookie) {
            setcookie(
                $name,
                $cookie['value'],
                [
                    'expires' => $cookie['expires'],
                    'path' => $cookie['path'],
                    'domain' => $cookie['domain'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httponly'],
                    'samesite' => $cookie['samesite'],
                ]
            );
        }

        // Enviar body
        echo $this->body;
    }

    /**
     * Determina si la respuesta es exitosa (2xx)
     *
     * @return bool true si es exitosa
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Determina si la respuesta es una redirección (3xx)
     *
     * @return bool true si es redirección
     */
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Determina si la respuesta es un error del cliente (4xx)
     *
     * @return bool true si es error de cliente
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Determina si la respuesta es un error del servidor (5xx)
     *
     * @return bool true si es error de servidor
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
}
