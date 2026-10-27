<?php

/**
 * LogMailer.php
 *
 * No envía nada: escribe el correo completo en el log de la aplicación. Sirve en desarrollo para ver,
 * por ejemplo, el enlace de recuperación de contraseña.
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

use BootZen\Core\Logger;

/**
 * Driver 'log' (MAIL_DRIVER=log).
 */
final class LogMailer implements Mailer
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function send(Message $message): void
    {
        $this->logger->info(
            "Correo para {$message->to}: {$message->subject}\n" . $message->text,
            ['to' => $message->to, 'subject' => $message->subject]
        );
    }
}
