<?php

/**
 * MimeMessage.php
 *
 * Convierte un Message en el texto que viaja por SMTP: cabeceras (con asunto y nombres en UTF-8
 * codificados según RFC 2047), cuerpo en texto y HTML (quoted-printable) y adjuntos (base64).
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.1.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

/**
 * Valida las direcciones y rechaza saltos de línea en cabeceras para evitar inyección de cabeceras.
 * Las copias ocultas (bcc) no aparecen en el resultado: solo viajan en el sobre SMTP.
 *
 * @internal Lo usa SmtpMailer.
 */
final class MimeMessage
{
    private const EOL = "\r\n";

    /**
     * Arma el correo completo con líneas terminadas en CRLF.
     *
     * @throws MailException Si una dirección no es válida o una cabecera trae saltos de línea
     */
    public static function build(Message $message, string $fromAddress, ?string $fromName = null): string
    {
        foreach ([$fromAddress, ...$message->recipients(), ...array_filter([$message->getReplyTo()])] as $address) {
            self::assertAddress($address);
        }

        self::assertHeader($message->subject);
        self::assertHeader((string) $fromName);

        $headers = [
            'Date' => date('r'),
            'Message-ID' => '<' . bin2hex(random_bytes(16)) . '@' . substr((string) strrchr($fromAddress, '@'), 1) . '>',
            'From' => self::mailbox($fromAddress, $fromName),
            'To' => $message->to,
        ];

        if ($message->getCc() !== []) {
            $headers['Cc'] = implode(', ', $message->getCc());
        }

        if ($message->getReplyTo() !== null) {
            $headers['Reply-To'] = $message->getReplyTo();
        }

        $headers['Subject'] = self::encodeHeader($message->subject, strlen('Subject: '));
        $headers['MIME-Version'] = '1.0';

        [$contentHeaders, $body] = self::content($message);

        $lines = [];
        foreach ([...$headers, ...$contentHeaders] as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        return implode(self::EOL, $lines) . self::EOL . self::EOL . $body;
    }

    /**
     * Cuerpo según lo que trae el mensaje: texto, texto + HTML y, si hay adjuntos, todo dentro de multipart/mixed.
     *
     * @return array{array<string, string>, string}
     */
    private static function content(Message $message): array
    {
        [$headers, $body] = $message->html === null
            ? self::textPart('text/plain', $message->text)
            : self::multipart('alternative', [
                self::textPart('text/plain', $message->text),
                self::textPart('text/html', $message->html),
            ]);

        if ($message->getAttachments() === []) {
            return [$headers, $body];
        }

        $parts = [[$headers, $body]];
        foreach ($message->getAttachments() as $attachment) {
            $parts[] = self::attachmentPart($attachment);
        }

        return self::multipart('mixed', $parts);
    }

    /**
     * @return array{array<string, string>, string}
     */
    private static function textPart(string $type, string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);

        return [
            ['Content-Type' => "{$type}; charset=UTF-8", 'Content-Transfer-Encoding' => 'quoted-printable'],
            quoted_printable_encode(str_replace("\n", self::EOL, $normalized)),
        ];
    }

    /**
     * @return array{array<string, string>, string}
     */
    private static function attachmentPart(Attachment $attachment): array
    {
        self::assertHeader($attachment->name);
        self::assertHeader($attachment->mime);

        $ascii = preg_match('/^[\x20-\x7E]*$/', $attachment->name) === 1;
        $quoted = addcslashes($attachment->name, '"\\');
        $filename = $ascii
            ? "filename=\"{$quoted}\""
            : "filename*=UTF-8''" . rawurlencode($attachment->name);

        return [
            [
                'Content-Type' => $attachment->mime . ($ascii ? "; name=\"{$quoted}\"" : ''),
                'Content-Transfer-Encoding' => 'base64',
                'Content-Disposition' => "attachment; {$filename}",
            ],
            rtrim(chunk_split(base64_encode($attachment->contents()), 76, self::EOL)),
        ];
    }

    /**
     * @param list<array{array<string, string>, string}> $parts
     * @return array{array<string, string>, string}
     */
    private static function multipart(string $subtype, array $parts): array
    {
        $boundary = '=_bootzen_' . bin2hex(random_bytes(12));
        $body = '';

        foreach ($parts as [$headers, $content]) {
            $body .= "--{$boundary}" . self::EOL;
            foreach ($headers as $name => $value) {
                $body .= "{$name}: {$value}" . self::EOL;
            }
            $body .= self::EOL . $content . self::EOL;
        }

        return [
            ['Content-Type' => "multipart/{$subtype}; boundary=\"{$boundary}\""],
            $body . "--{$boundary}--",
        ];
    }

    private static function mailbox(string $address, ?string $name): string
    {
        if ($name === null || $name === '') {
            return $address;
        }

        $display = preg_match('/^[\x20-\x7E]*$/', $name) === 1
            ? '"' . addcslashes($name, '"\\') . '"'
            : self::encodeHeader($name, strlen('From: '));

        return "{$display} <{$address}>";
    }

    /**
     * Codifica en RFC 2047 (UTF-8, base64) si el texto no es ASCII; si lo es, lo deja igual.
     */
    private static function encodeHeader(string $value, int $indent): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }

        return mb_encode_mimeheader($value, 'UTF-8', 'B', self::EOL, $indent);
    }

    /**
     * @throws MailException
     */
    private static function assertAddress(string $address): void
    {
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new MailException("Dirección de correo inválida: {$address}");
        }
    }

    /**
     * @throws MailException
     */
    private static function assertHeader(string $value): void
    {
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new MailException('Una cabecera del correo trae saltos de línea.');
        }
    }
}
