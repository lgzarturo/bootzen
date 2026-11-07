<?php

/**
 * TemporaryUrls.php
 *
 * Disco que genera sus propias URLs temporales (como las prefirmadas de S3). Los demás usan la ruta
 * firmada de BootZen (/_bootzen/archivo).
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

interface TemporaryUrls
{
    /**
     * URL que deja descargar el archivo hasta $expires (timestamp). Con $downloadName lo baja con ese nombre.
     */
    public function temporaryUrl(string $path, int $expires, ?string $downloadName = null): string;
}
