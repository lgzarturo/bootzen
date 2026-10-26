<?php

/**
 * HttpException.php
 *
 * Excepción que representa una respuesta HTTP de error (404, 403, 419, 500...).
 * La lanza el helper abort() y la convierte en respuesta Application::handleException().
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use RuntimeException;
use Throwable;

/**
 * Error HTTP con código de estado y cabeceras opcionales.
 *
 * @example throw new HttpException(404, 'Tarea no encontrada');
 */
class HttpException extends RuntimeException
{
    /**
     * @param int $statusCode Código de estado HTTP
     * @param string $message Mensaje para el usuario
     * @param array<string, string> $headers Cabeceras adicionales de la respuesta
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Obtiene el código de estado HTTP
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtiene las cabeceras adicionales
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
