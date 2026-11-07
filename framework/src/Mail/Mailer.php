<?php

/**
 * Mailer.php
 *
 * Contrato de envío de correo. Drivers: log (escribe el correo en storage/logs), smtp (envío real)
 * y array (tests).
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

interface Mailer
{
    /**
     * Envía el correo.
     *
     * @throws MailException Si no se pudo armar o enviar
     */
    public function send(Message $message): void;
}
