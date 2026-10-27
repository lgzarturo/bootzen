<?php

/**
 * ValidationException.php
 *
 * Error de validación (422). En formularios, StartSession la convierte en una redirección a la página
 * anterior con los errores y el input; en peticiones JSON responde 422 con los errores.
 *
 * @package    BootZen\Validation
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Validation;

use BootZen\Core\HttpException;

/**
 * Lleva los errores por campo.
 *
 * @example throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
 */
final class ValidationException extends HttpException
{
    /**
     * @param array<string, list<string>> $errors Campo => mensajes
     */
    public function __construct(private readonly array $errors, string $message = 'Los datos enviados no son válidos.')
    {
        parent::__construct(422, $message);
    }

    /**
     * Crea la excepción con mensajes escritos a mano.
     *
     * @param array<string, string|list<string>> $messages Campo => mensaje o mensajes
     */
    public static function withMessages(array $messages): self
    {
        $errors = [];
        foreach ($messages as $field => $value) {
            $errors[$field] = is_array($value) ? $value : [$value];
        }

        return new self($errors);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
