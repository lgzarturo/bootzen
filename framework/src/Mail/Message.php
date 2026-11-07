<?php

/**
 * Message.php
 *
 * Correo a enviar: destinatario, asunto y cuerpo en texto (y HTML opcional), más copias, respuesta
 * y adjuntos opcionales. El remitente sale de config/mail.php salvo que se indique otro con from().
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

/**
 * Los métodos cc(), bcc(), replyTo(), from(), attach() y attachData() modifican el mensaje y lo
 * devuelven, así que se pueden encadenar.
 *
 * @example mailer()->send((new Message('ana@ejemplo.com', 'Tu factura', $texto))->attach(storage_path('app/f-12.pdf')));
 */
final class Message
{
    /**
     * @var list<string>
     */
    private array $cc = [];

    /**
     * @var list<string>
     */
    private array $bcc = [];

    private ?string $replyTo = null;

    private ?string $fromAddress = null;

    private ?string $fromName = null;

    /**
     * @var list<Attachment>
     */
    private array $attachments = [];

    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $text,
        public readonly ?string $html = null
    ) {
    }

    /**
     * Agrega destinatarios en copia (visibles para todos).
     */
    public function cc(string ...$emails): self
    {
        array_push($this->cc, ...array_values($emails));

        return $this;
    }

    /**
     * Agrega destinatarios en copia oculta (no aparecen en las cabeceras).
     */
    public function bcc(string ...$emails): self
    {
        array_push($this->bcc, ...array_values($emails));

        return $this;
    }

    /**
     * Dirección a la que llegan las respuestas.
     */
    public function replyTo(string $email): self
    {
        $this->replyTo = $email;

        return $this;
    }

    /**
     * Remitente para este correo, en lugar del de config/mail.php.
     */
    public function from(string $email, ?string $name = null): self
    {
        $this->fromAddress = $email;
        $this->fromName = $name;

        return $this;
    }

    /**
     * Adjunta un archivo del disco. El nombre y el tipo se deducen del archivo si no se indican.
     *
     * @throws MailException Si el archivo no existe o no se puede leer
     */
    public function attach(string $path, ?string $name = null, ?string $mime = null): self
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new MailException("No existe el archivo para adjuntar: {$path}");
        }

        $detected = function_exists('mime_content_type') ? mime_content_type($path) : false;
        $this->attachments[] = new Attachment(
            $name ?? basename($path),
            $mime ?? ($detected !== false ? $detected : 'application/octet-stream'),
            path: $path
        );

        return $this;
    }

    /**
     * Adjunta contenido generado en memoria (por ejemplo un CSV o un PDF recién creado).
     */
    public function attachData(string $contents, string $name, string $mime = 'application/octet-stream'): self
    {
        $this->attachments[] = new Attachment($name, $mime, data: $contents);

        return $this;
    }

    /**
     * Destinatarios en copia.
     *
     * @return list<string>
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * Destinatarios en copia oculta.
     *
     * @return list<string>
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /**
     * Dirección para las respuestas, si se indicó una.
     */
    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    /**
     * Remitente propio de este correo, o null para usar el de config/mail.php.
     *
     * @return array{address: string, name: string|null}|null
     */
    public function getFrom(): ?array
    {
        return $this->fromAddress === null ? null : ['address' => $this->fromAddress, 'name' => $this->fromName];
    }

    /**
     * Adjuntos en el orden en que se agregaron.
     *
     * @return list<Attachment>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Todas las direcciones que reciben el correo: destinatario, copias y copias ocultas, sin repetir.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        return array_values(array_unique([$this->to, ...$this->cc, ...$this->bcc]));
    }
}
