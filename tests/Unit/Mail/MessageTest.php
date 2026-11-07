<?php

declare(strict_types=1);

use BootZen\Core\Logger;
use BootZen\Mail\LogMailer;
use BootZen\Mail\MailException;
use BootZen\Mail\Message;
use BootZen\Mail\MimeMessage;

/**
 * Separa un correo MIME en cabeceras (sin plegar) y cuerpo.
 *
 * @return array{array<string, string>, string}
 */
function parseMime(string $raw): array
{
    [$head, $body] = explode("\r\n\r\n", $raw, 2);
    $headers = [];

    foreach (explode("\r\n", (string) preg_replace("/\r\n[ \t]+/", ' ', $head)) as $line) {
        [$name, $value] = explode(': ', $line, 2);
        $headers[$name] = $value;
    }

    return [$headers, $body];
}

/**
 * Partes de un multipart: cada una con sus cabeceras y su cuerpo.
 *
 * @return list<array{array<string, string>, string}>
 */
function mimeParts(string $body, string $contentType): array
{
    preg_match('/boundary="([^"]+)"/', $contentType, $match);
    $chunks = explode('--' . $match[1], $body);

    return array_values(array_map(
        static fn (string $chunk): array => parseMime(trim($chunk, "\r\n")),
        array_filter(array_slice($chunks, 1, -1), static fn (string $chunk): bool => trim($chunk) !== '')
    ));
}

it('mantiene el constructor de siempre y suma copias, respuesta y remitente', function (): void {
    $message = (new Message('ana@ejemplo.com', 'Hola', 'Texto'))
        ->cc('luis@ejemplo.com', 'eva@ejemplo.com')
        ->bcc('auditoria@ejemplo.com', 'ana@ejemplo.com')
        ->replyTo('soporte@ejemplo.com')
        ->from('ventas@ejemplo.com', 'Ventas');

    expect($message->to)->toBe('ana@ejemplo.com')
        ->and($message->getCc())->toBe(['luis@ejemplo.com', 'eva@ejemplo.com'])
        ->and($message->getBcc())->toBe(['auditoria@ejemplo.com', 'ana@ejemplo.com'])
        ->and($message->getReplyTo())->toBe('soporte@ejemplo.com')
        ->and($message->getFrom())->toBe(['address' => 'ventas@ejemplo.com', 'name' => 'Ventas'])
        ->and($message->recipients())->toBe(['ana@ejemplo.com', 'luis@ejemplo.com', 'eva@ejemplo.com', 'auditoria@ejemplo.com']);
});

it('adjunta archivos del disco y contenido en memoria', function (): void {
    $dir = makeTempDir('bootzen-adjuntos');
    writeFile($dir . '/factura.pdf', "%PDF-1.4\n% factura");

    $message = (new Message('ana@ejemplo.com', 'Factura', 'Adjunta'))
        ->attach($dir . '/factura.pdf')
        ->attach($dir . '/factura.pdf', 'F-12.pdf', 'application/pdf')
        ->attachData("id,total\n1,10", 'pedidos.csv', 'text/csv');
    $attachments = $message->getAttachments();

    expect(array_map(static fn ($a): string => $a->name, $attachments))->toBe(['factura.pdf', 'F-12.pdf', 'pedidos.csv'])
        ->and($attachments[0]->mime)->toBe('application/pdf')
        ->and($attachments[2]->contents())->toBe("id,total\n1,10")
        ->and(fn () => $message->attach($dir . '/no-existe.pdf'))->toThrow(MailException::class, 'No existe el archivo para adjuntar');

    unlink($dir . '/factura.pdf');

    expect(fn () => $attachments[0]->contents())->toThrow(MailException::class, 'No se pudo leer el adjunto factura.pdf');

    removeDir($dir);
});

it('arma un correo de solo texto con asunto y remitente en UTF-8', function (): void {
    $raw = MimeMessage::build(
        (new Message('ana@ejemplo.com', 'Tu pedido está en camino 🚚', "Hola Ana,\nsalió hoy.\n.\nGracias"))->cc('luis@ejemplo.com')->bcc('oculto@ejemplo.com'),
        'ventas@tienda.mx',
        'Tienda Ñandú'
    );
    [$headers, $body] = parseMime($raw);

    expect($headers['To'])->toBe('ana@ejemplo.com')
        ->and($headers['Cc'])->toBe('luis@ejemplo.com')
        ->and($raw)->not->toContain('oculto@ejemplo.com')
        ->and(iconv_mime_decode($headers['Subject'], 0, 'UTF-8'))->toBe('Tu pedido está en camino 🚚')
        ->and(iconv_mime_decode($headers['From'], 0, 'UTF-8'))->toBe('Tienda Ñandú <ventas@tienda.mx>')
        ->and($headers['Message-ID'])->toMatch('/^<[a-f0-9]{32}@tienda\.mx>$/')
        ->and($headers['Content-Type'])->toBe('text/plain; charset=UTF-8')
        ->and($headers['Content-Transfer-Encoding'])->toBe('quoted-printable')
        ->and(quoted_printable_decode($body))->toBe("Hola Ana,\r\nsalió hoy.\r\n.\r\nGracias")
        ->and($raw)->not->toMatch("/[^\r]\n/");
});

it('arma texto y HTML con adjuntos en partes anidadas', function (): void {
    $raw = MimeMessage::build(
        (new Message('ana@ejemplo.com', 'Factura', 'Va tu factura', '<p>Va tu <b>factura</b></p>'))
            ->replyTo('cobranza@tienda.mx')
            ->attachData('%PDF datos', 'F-12.pdf', 'application/pdf')
            ->attachData('día', 'reseña ñ.txt', 'text/plain'),
        'ventas@tienda.mx',
        'Tienda'
    );
    [$headers, $body] = parseMime($raw);
    [$alternative, $pdf, $txt] = mimeParts($body, $headers['Content-Type']);
    [$text, $html] = mimeParts($alternative[1], $alternative[0]['Content-Type']);

    expect($headers['From'])->toBe('"Tienda" <ventas@tienda.mx>')
        ->and($headers['Reply-To'])->toBe('cobranza@tienda.mx')
        ->and($headers['Content-Type'])->toStartWith('multipart/mixed; boundary=')
        ->and($alternative[0]['Content-Type'])->toStartWith('multipart/alternative; boundary=')
        ->and(quoted_printable_decode($text[1]))->toBe('Va tu factura')
        ->and($html[0]['Content-Type'])->toBe('text/html; charset=UTF-8')
        ->and(quoted_printable_decode($html[1]))->toBe('<p>Va tu <b>factura</b></p>')
        ->and($pdf[0]['Content-Disposition'])->toBe('attachment; filename="F-12.pdf"')
        ->and($pdf[0]['Content-Type'])->toBe('application/pdf; name="F-12.pdf"')
        ->and(base64_decode($pdf[1]))->toBe('%PDF datos')
        ->and($txt[0]['Content-Disposition'])->toBe("attachment; filename*=UTF-8''rese%C3%B1a%20%C3%B1.txt")
        ->and(base64_decode($txt[1]))->toBe('día');
});

it('parte en líneas de 76 caracteres los adjuntos grandes', function (): void {
    $raw = MimeMessage::build((new Message('ana@ejemplo.com', 'Datos', 'Adjunto'))->attachData(random_bytes(5000), 'datos.bin'), 'ventas@tienda.mx');

    foreach (explode("\r\n", $raw) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(998);
    }

    expect($raw)->toMatch('/^[A-Za-z0-9+\/]{76}\r$/m');
});

it('rechaza direcciones inválidas y cabeceras con saltos de línea', function (Message $message, string $from, string $error): void {
    expect(fn () => MimeMessage::build($message, $from))->toThrow(MailException::class, $error);
})->with([
    'destinatario' => [new Message('no-es-correo', 'Hola', 'x'), 'ventas@tienda.mx', 'Dirección de correo inválida: no-es-correo'],
    'copia' => [(new Message('ana@ejemplo.com', 'Hola', 'x'))->cc("luis@ejemplo.com\r\nBcc: todos@ejemplo.com"), 'ventas@tienda.mx', 'Dirección de correo inválida'],
    'remitente' => [new Message('ana@ejemplo.com', 'Hola', 'x'), 'ventas', 'Dirección de correo inválida: ventas'],
    'asunto' => [new Message('ana@ejemplo.com', "Hola\r\nBcc: todos@ejemplo.com", 'x'), 'ventas@tienda.mx', 'saltos de línea'],
    'adjunto' => [(new Message('ana@ejemplo.com', 'Hola', 'x'))->attachData('x', "a.txt\r\nX-Malo: 1"), 'ventas@tienda.mx', 'saltos de línea'],
]);

it('anota copias y adjuntos en el log', function (): void {
    $dir = makeTempDir('bootzen-maillog');
    (new LogMailer(new Logger($dir)))->send((new Message('ana@ejemplo.com', 'Factura', 'Texto'))->cc('luis@ejemplo.com')->attachData('x', 'F-12.pdf'));

    $log = (string) file_get_contents((string) glob($dir . '/*.log')[0]);

    expect($log)->toContain('Correo para ana@ejemplo.com: Factura')
        ->toContain('luis@ejemplo.com')
        ->toContain('F-12.pdf')
        ->not->toContain('reply_to');

    removeDir($dir);
});
