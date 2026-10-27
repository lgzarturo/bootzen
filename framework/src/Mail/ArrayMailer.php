<?php

/**
 * ArrayMailer.php
 *
 * Guarda los correos en memoria para revisarlos en los tests (MAIL_DRIVER=array).
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
 * @example expect(app(Mailer::class)->sent())->toHaveCount(1);
 */
final class ArrayMailer implements Mailer
{
    /**
     * @var list<Message>
     */
    private array $sent = [];

    public function send(Message $message): void
    {
        $this->sent[] = $message;
    }

    /**
     * @return list<Message>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
