<?php

/**
 * ViewException.php
 *
 * Error al compilar o renderizar una vista. Indica la vista original y la línea, no el archivo compilado.
 *
 * @package    BootZen\View
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\View;

use RuntimeException;
use Throwable;

/**
 * Excepción de vistas con la ubicación en la vista original.
 */
class ViewException extends RuntimeException
{
    /**
     * @param string $message Mensaje con la vista y la línea
     * @param string $viewFile Ruta absoluta de la vista original ('' si no aplica)
     * @param int $viewLine Línea en la vista original (0 si no se conoce)
     */
    public function __construct(
        string $message,
        private readonly string $viewFile = '',
        private readonly int $viewLine = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getViewFile(): string
    {
        return $this->viewFile;
    }

    public function getViewLine(): int
    {
        return $this->viewLine;
    }
}
