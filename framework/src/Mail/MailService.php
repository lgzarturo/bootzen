<?php

/**
 * MailService.php
 *
 * Lo que devuelve mailer(): envía con el driver configurado (send) o deja el correo en la cola para
 * que lo envíe el worker (queue), así la petición no espera al servidor SMTP.
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

use BootZen\Queue\Queue;
use Closure;

/**
 * Con QUEUE_DRIVER=sync, queue() envía en el momento (igual que send).
 *
 * @example mailer()->queue(new Message($cliente->email, 'Tu pedido', $texto));
 */
final class MailService implements Mailer
{
    /**
     * @param Closure(): Queue $queue La cola se resuelve solo si se usa
     */
    public function __construct(private readonly Mailer $mailer, private readonly Closure $queue)
    {
    }

    /**
     * Envía el correo ahora.
     *
     * @throws MailException Si no se pudo armar o enviar
     */
    public function send(Message $message): void
    {
        $this->mailer->send($message);
    }

    /**
     * Encola el correo para enviarlo en segundo plano (con $delay, no antes de esos segundos). Devuelve el id del job.
     */
    public function queue(Message $message, int $delay = 0): int
    {
        return ($this->queue)()->push(new SendQueuedMail($message->toArray()), $delay);
    }
}
