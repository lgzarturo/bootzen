<?php

/**
 * Quietly.php
 *
 * Ejecuta funciones de PHP que avisan con warnings (archivos, sockets, FTP) sin que el aviso salga en
 * pantalla, y devuelve el texto del aviso para incluirlo en un mensaje de error claro.
 *
 * @package    BootZen\Support
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Support;

use Closure;

/**
 * @internal Lo usan los drivers de archivos y de correo.
 */
final class Quietly
{
    /**
     * @template T
     * @param Closure(): T $callback
     * @return array{T, string} El resultado y el último aviso ('' si no hubo)
     */
    public static function run(Closure $callback): array
    {
        $warning = '';
        set_error_handler(static function (int $level, string $message) use (&$warning): bool {
            $warning = trim((string) preg_replace('/^[a-z0-9_]+\(\)(?: \[[^\]]*\])?: /i', '', $message));

            return true;
        });

        try {
            return [$callback(), $warning];
        } finally {
            restore_error_handler();
        }
    }
}
