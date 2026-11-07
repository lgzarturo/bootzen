<?php

/**
 * Disk.php
 *
 * Contrato de un disco de archivos: disco local, S3 y compatibles, FTP o SFTP. Las rutas son
 * relativas a la raíz del disco ('facturas/f-12.pdf'). Se usa a través de storage().
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

interface Disk
{
    /**
     * Guarda el contenido (crea las carpetas que falten). $mime es el tipo para servirlo.
     *
     * @throws StorageException
     */
    public function put(string $path, string $contents, ?string $mime = null): void;

    /**
     * Lee el archivo completo.
     *
     * @throws StorageException Si no existe o no se puede leer
     */
    public function get(string $path): string;

    public function exists(string $path): bool;

    /**
     * Borra el archivo. Devuelve false si no existía.
     *
     * @throws StorageException Si existe pero no se puede borrar
     */
    public function delete(string $path): bool;

    /**
     * Tamaño en bytes.
     *
     * @throws StorageException Si no existe
     */
    public function size(string $path): int;

    /**
     * URL pública del archivo, o null si el disco no tiene una (por ejemplo, un disco privado).
     */
    public function url(string $path): ?string;
}
