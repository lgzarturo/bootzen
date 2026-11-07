<?php

/**
 * SendQueuedMail.php
 *
 * Job que envía un correo encolado con mailer()->queue(). Los adjuntos del disco se leen al enviar,
 * así que el archivo debe seguir existiendo; los de memoria viajan dentro de la cola.
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

use BootZen\Queue\Job;

/**
 * @internal Lo crea MailService::queue().
 */
final class SendQueuedMail extends Job
{
    /**
     * @param array<string, mixed> $message Message::toArray()
     */
    public function __construct(public readonly array $message)
    {
    }

    public function handle(Mailer $mailer): void
    {
        $mailer->send(Message::fromArray($this->message));
    }
}
