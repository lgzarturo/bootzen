<?php

/**
 * Attachment.php
 *
 * Archivo adjunto de un Message: nombre, tipo MIME y contenido (leído de disco al enviar o en memoria).
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.1.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

/**
 * Se crea con Message::attach() o Message::attachData(); no hace falta instanciarlo a mano.
 */
final class Attachment
{
    public function __construct(
        public readonly string $name,
        public readonly string $mime,
        private readonly ?string $path = null,
        private readonly ?string $data = null
    ) {
    }

    /**
     * Datos para guardarlo en la cola: la ruta si viene del disco, el contenido en base64 si no.
     *
     * @return array{name: string, mime: string, path: string|null, data: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'mime' => $this->mime,
            'path' => $this->path,
            'data' => $this->data === null ? null : base64_encode($this->data),
        ];
    }

    /**
     * Contenido del archivo.
     *
     * @throws MailException Si el archivo ya no se puede leer
     */
    public function contents(): string
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $contents = $this->path !== null && is_readable($this->path) ? file_get_contents($this->path) : false;

        if ($contents === false) {
            throw new MailException("No se pudo leer el adjunto {$this->name}.");
        }

        return $contents;
    }
}
