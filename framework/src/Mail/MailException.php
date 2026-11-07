<?php

/**
 * MailException.php
 *
 * El correo no se pudo armar o enviar: dirección inválida, adjunto que no existe o un error del
 * servidor SMTP (conexión, autenticación, destinatario rechazado). El mensaje nunca incluye la contraseña.
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.1.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

use RuntimeException;

/**
 * La lanza mailer()->send() cuando el correo no sale; atrápala donde un fallo de envío no deba romper la página.
 *
 * @example try { mailer()->send($correo); } catch (MailException $e) { logger($e->getMessage(), 'error'); }
 */
final class MailException extends RuntimeException
{
}
