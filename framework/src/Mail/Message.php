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
     * El mensaje como array de valores simples, para guardarlo en la cola (mailer()->queue()).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'subject' => $this->subject,
            'text' => $this->text,
            'html' => $this->html,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => $this->replyTo,
            'from' => $this->getFrom(),
            'attachments' => array_map(static fn (Attachment $attachment): array => $attachment->toArray(), $this->attachments),
        ];
    }

    /**
     * Reconstruye un mensaje guardado con toArray().
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (mixed $value): ?string => is_string($value) ? $value : null;
        $list = static fn (mixed $value): array => is_array($value) ? array_values(array_filter($value, 'is_string')) : [];

        $message = new self((string) $string($data['to'] ?? ''), (string) $string($data['subject'] ?? ''), (string) $string($data['text'] ?? ''), $string($data['html'] ?? null));
        $message->cc = $list($data['cc'] ?? []);
        $message->bcc = $list($data['bcc'] ?? []);
        $message->replyTo = $string($data['reply_to'] ?? null);

        if (is_array($data['from'] ?? null) && is_string($data['from']['address'] ?? null)) {
            $message->from($data['from']['address'], $string($data['from']['name'] ?? null));
        }

        foreach (is_array($data['attachments'] ?? null) ? $data['attachments'] : [] as $attachment) {
            if (! is_array($attachment) || ! is_string($attachment['name'] ?? null)) {
                continue;
            }

            $mime = (string) ($string($attachment['mime'] ?? null) ?? 'application/octet-stream');
            $content = $string($attachment['data'] ?? null);
            $message->attachments[] = $content !== null
                ? new Attachment($attachment['name'], $mime, data: (string) base64_decode($content, true))
                : new Attachment($attachment['name'], $mime, path: $string($attachment['path'] ?? null));
        }

        return $message;
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
