<?php

/**
 * MigrationException.php
 *
 * Falla de una migración: nombra la migración y conserva el error original.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use RuntimeException;
use Throwable;

/**
 * Error al aplicar o revertir una migración.
 */
final class MigrationException extends RuntimeException
{
    public function __construct(string $message, Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
    }
}
