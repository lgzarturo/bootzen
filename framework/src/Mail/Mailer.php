<?php

/**
 * Mailer.php
 *
 * Contrato de envío de correo. Drivers: log (escribe el correo en storage/logs) y array (tests).
 * SMTP llega en una versión posterior.
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
    public function send(Message $message): void;
}
