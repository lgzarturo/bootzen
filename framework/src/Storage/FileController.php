<?php

/**
 * FileController.php
 *
 * Atiende /_bootzen/archivo: entrega un archivo privado si la URL temporal (temporaryUrl()) tiene una
 * firma válida y no venció. Sirve para discos sin URLs propias (local, FTP y SFTP).
 *
 * @package    BootZen\Storage
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.3.0
 */

declare(strict_types=1);

namespace BootZen\Storage;

use BootZen\Core\HttpException;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Responde 403 si la firma no es válida o venció y 404 si el archivo ya no existe. Las imágenes,
 * PDF y textos se muestran en el navegador; lo demás se descarga.
 *
 * @internal La registra Application.
 */
final class FileController
{
    public function __construct(private readonly StorageManager $storage, private readonly ?UrlSigner $signer = null)
    {
    }

    public function show(Request $request): Response
    {
        $verified = $this->signer?->verify($request->getQuery(), time());

        if ($verified === null) {
            throw new HttpException(403, 'El enlace no es válido o ya venció.');
        }

        [$disk, $path, $name] = $verified;

        try {
            return $this->storage->disk($disk)->download($path, $name, $name === null);
        } catch (StorageException) {
            throw new HttpException(404, 'El archivo ya no existe.');
        }
    }
}
