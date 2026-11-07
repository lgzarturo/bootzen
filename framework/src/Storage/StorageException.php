<?php

/**
 * StorageException.php
 *
 * Un archivo no se pudo guardar, leer o borrar: no existe, falta permiso, el servidor remoto
 * respondió con error o la configuración del disco está incompleta.
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

use RuntimeException;

/**
 * La lanzan los discos y storage(); el mensaje nunca incluye credenciales.
 */
final class StorageException extends RuntimeException
{
}
