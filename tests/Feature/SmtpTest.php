<?php

declare(strict_types=1);

/**
 * Integración con un servidor SMTP real (Mailpit). Se ejecuta solo si existe BOOTZEN_TEST_SMTP_HOST (CI la define).
 *
 * BOOTZEN_TEST_SMTP_HOST, _PORT, _USERNAME, _PASSWORD y _API (la API HTTP de Mailpit, por ejemplo
 * http://127.0.0.1:8025). El servidor debe exigir STARTTLS con un certificado autofirmado.
 */

use BootZen\Mail\MailException;
use BootZen\Mail\Message;
use BootZen\Mail\SmtpMailer;

function smtpEnv(string $key, string $default = ''): string
{
    $value = getenv('BOOTZEN_TEST_SMTP_' . $key);

    return $value === false ? $default : $value;
}

/**
 * Llama a la API de Mailpit y devuelve el cuerpo (decodificado si es JSON).
 */
function mailpit(string $path, string $method = 'GET'): mixed
{
    $body = file_get_contents(rtrim(smtpEnv('API'), '/') . $path, false, stream_context_create(['http' => ['method' => $method]]));

    if ($body === false) {
        throw new RuntimeException("Mailpit no respondió en {$path}.");
    }

    $json = json_decode($body, true);

    return json_last_error() === JSON_ERROR_NONE ? $json : $body;
}

/**
 * @param array<string, mixed> $overrides
 */
function smtpMailer(array $overrides = []): SmtpMailer
{
    return new SmtpMailer(...[
        'host' => smtpEnv('HOST'),
        'port' => (int) smtpEnv('PORT', '1025'),
        'encryption' => 'tls',
        'username' => smtpEnv('USERNAME'),
        'password' => smtpEnv('PASSWORD'),
        'fromAddress' => 'ventas@tienda.mx',
        'fromName' => 'Tienda Ñandú',
        'verifyPeer' => false,
        ...$overrides,
    ]);
}

beforeEach(function (): void {
    if (smtpEnv('HOST') === '') {
        $this->markTestSkipped('SMTP no configurado (BOOTZEN_TEST_SMTP_HOST).');
    }

    mailpit('/api/v1/messages', 'DELETE');
});

it('entrega por STARTTLS un correo completo que llega intacto', function (): void {
    $pdf = "%PDF-1.4\n" . random_bytes(3000);

    smtpMailer()->send(
        (new Message('ana@ejemplo.com', 'Tu factura está lista ✅', "Hola Ana,\n.\nVa tu factura.", '<p>Hola <b>Ana</b></p>'))
            ->cc('luis@ejemplo.com')
            ->bcc('archivo@tienda.mx')
            ->replyTo('cobranza@tienda.mx')
            ->attachData($pdf, 'F-12.pdf', 'application/pdf')
            ->attachData('día,total', 'reseña.csv', 'text/csv')
    );

    $list = mailpit('/api/v1/messages');
    $message = mailpit('/api/v1/message/' . $list['messages'][0]['ID']);
    $attachments = array_column($message['Attachments'], null, 'FileName');

    expect($list['total'])->toBe(1)
        ->and($message['Subject'])->toBe('Tu factura está lista ✅')
        ->and($message['From'])->toBe(['Name' => 'Tienda Ñandú', 'Address' => 'ventas@tienda.mx'])
        ->and(array_column($message['To'], 'Address'))->toBe(['ana@ejemplo.com'])
        ->and(array_column($message['Cc'], 'Address'))->toBe(['luis@ejemplo.com'])
        ->and(array_column($message['Bcc'], 'Address'))->toBe(['archivo@tienda.mx'])
        ->and(array_column($message['ReplyTo'], 'Address'))->toBe(['cobranza@tienda.mx'])
        ->and(str_replace("\r\n", "\n", $message['Text']))->toBe("Hola Ana,\n.\nVa tu factura.")
        ->and($message['HTML'])->toBe('<p>Hola <b>Ana</b></p>')
        ->and(array_keys($attachments))->toBe(['F-12.pdf', 'reseña.csv'])
        ->and(mailpit('/api/v1/message/' . $message['ID'] . '/part/' . $attachments['F-12.pdf']['PartID']))->toBe($pdf)
        ->and(mailpit('/api/v1/message/' . $message['ID'] . '/part/' . $attachments['reseña.csv']['PartID']))->toBe('día,total');
});

it('rechaza el certificado autofirmado si se verifica', function (): void {
    expect(fn () => smtpMailer(['verifyPeer' => true])->send(new Message('ana@ejemplo.com', 'Hola', 'x')))
        ->toThrow(MailException::class, 'No se pudo cifrar la conexión');
});

it('avisa si la contraseña es incorrecta o si falta cifrar', function (): void {
    expect(fn () => smtpMailer(['password' => 'otra'])->send(new Message('ana@ejemplo.com', 'Hola', 'x')))
        ->toThrow(MailException::class, 'Revisa MAIL_USERNAME y MAIL_PASSWORD.')
        ->and(fn () => smtpMailer(['encryption' => 'none', 'username' => null])->send(new Message('ana@ejemplo.com', 'Hola', 'x')))
        ->toThrow(MailException::class, 'El servidor SMTP rechazó MAIL FROM');

    expect(mailpit('/api/v1/messages')['total'])->toBe(0);
});
