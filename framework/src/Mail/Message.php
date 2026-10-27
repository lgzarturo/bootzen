<?php

/**
 * Message.php
 *
 * Correo a enviar: destinatario, asunto y cuerpo en texto (y HTML opcional).
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
 * @example mailer()->send(new Message('ana@ejemplo.com', 'Bienvenida', "Hola Ana..."));
 */
final class Message
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $text,
        public readonly ?string $html = null
    ) {
    }
}
