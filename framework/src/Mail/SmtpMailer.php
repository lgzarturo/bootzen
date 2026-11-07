<?php

/**
 * SmtpMailer.php
 *
 * Driver 'smtp' (MAIL_DRIVER=smtp): envía el correo a un servidor SMTP con STARTTLS (puerto 587),
 * TLS directo (465) o sin cifrar (servidores locales), con autenticación PLAIN o LOGIN.
 * Sin librerías: sockets de PHP y la extensión openssl para el cifrado.
 *
 * @package    BootZen\Mail
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.1.0
 */

declare(strict_types=1);

namespace BootZen\Mail;

use Closure;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Abre una conexión por correo. Cualquier error (conexión, TLS, credenciales, destinatario rechazado)
 * lanza MailException con la respuesta del servidor, nunca con la contraseña.
 *
 * @example new SmtpMailer('smtp.ejemplo.com', 587, 'tls', 'usuario', 'clave', 'hola@ejemplo.com', 'Mi tienda')
 */
final class SmtpMailer implements Mailer
{
    public const ENCRYPTIONS = ['tls', 'ssl', 'none'];

    /**
     * @param string $encryption tls (STARTTLS), ssl (TLS desde el inicio) o none
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly string $encryption = 'tls',
        private readonly ?string $username = null,
        #[SensitiveParameter]
        private readonly ?string $password = null,
        private readonly string $fromAddress = '',
        private readonly ?string $fromName = null,
        private readonly float $timeout = 10.0,
        private readonly bool $verifyPeer = true,
        private readonly string $heloDomain = 'localhost'
    ) {
        if (! in_array($encryption, self::ENCRYPTIONS, true)) {
            throw new InvalidArgumentException("MAIL_ENCRYPTION [{$encryption}] inválido. Usa tls (puerto 587), ssl (465) o none.");
        }
    }

    /**
     * Crea el driver desde config('mail').
     *
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config, string $heloDomain = 'localhost'): self
    {
        $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
        $from = is_array($config['from'] ?? null) ? $config['from'] : [];
        $string = static fn (mixed $value): ?string => is_scalar($value) && (string) $value !== '' ? (string) $value : null;

        return new self(
            $string($smtp['host'] ?? null) ?? '127.0.0.1',
            (int) ($string($smtp['port'] ?? null) ?? 587),
            strtolower($string($smtp['encryption'] ?? null) ?? 'tls'),
            $string($smtp['username'] ?? null),
            $string($smtp['password'] ?? null),
            $string($from['address'] ?? null) ?? '',
            $string($from['name'] ?? null),
            (float) ($string($smtp['timeout'] ?? null) ?? 10),
            filter_var($smtp['verify_peer'] ?? true, FILTER_VALIDATE_BOOL),
            $heloDomain !== '' ? $heloDomain : 'localhost'
        );
    }

    public function send(Message $message): void
    {
        $from = $message->getFrom() ?? ['address' => $this->fromAddress, 'name' => $this->fromName];

        if ($from['address'] === '') {
            throw new MailException('Falta el remitente: define MAIL_FROM_ADDRESS en .env.');
        }

        // Se arma antes de conectar: una dirección inválida o un adjunto perdido no abren conexión
        $data = MimeMessage::build($message, $from['address'], $from['name']);
        $socket = $this->connect();

        try {
            $this->expect($socket, [220], 'saludo');
            $capabilities = $this->ehlo($socket);

            if ($this->encryption === 'tls') {
                $capabilities = $this->startTls($socket, $capabilities);
            }

            if ($this->username !== null) {
                $this->authenticate($socket, $capabilities);
            }

            $this->command($socket, "MAIL FROM:<{$from['address']}>", [250]);

            foreach ($message->recipients() as $recipient) {
                $this->command($socket, "RCPT TO:<{$recipient}>", [250, 251], "el destinatario {$recipient}");
            }

            $this->command($socket, 'DATA', [354]);
            // Una línea que empieza con punto se duplica para que el servidor no la tome como fin del mensaje
            $this->write($socket, (string) preg_replace('/^\./m', '..', $data) . "\r\n.\r\n");
            $this->expect($socket, [250], 'envío del mensaje');

            $this->write($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return resource
     */
    private function connect()
    {
        $context = stream_context_create(['ssl' => [
            'peer_name' => $this->host,
            'verify_peer' => $this->verifyPeer,
            'verify_peer_name' => $this->verifyPeer,
            'allow_self_signed' => ! $this->verifyPeer,
        ]]);

        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $code = 0;
        $error = '';
        $address = "{$scheme}://{$this->host}:{$this->port}";
        [$socket, $warning] = self::quietly(function () use ($address, $context, &$code, &$error) {
            return stream_socket_client($address, $code, $error, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        });

        if (! is_resource($socket)) {
            $detail = $warning !== '' && ($this->encryption === 'ssl' || $error === '') ? $warning : $error;

            throw new MailException("No se pudo conectar al servidor SMTP {$this->host}:{$this->port} ({$detail}).");
        }

        stream_set_timeout($socket, (int) ceil($this->timeout));

        return $socket;
    }

    /**
     * @param resource $socket
     * @return list<string> Capacidades que anuncia el servidor, en mayúsculas
     */
    private function ehlo($socket): array
    {
        $lines = $this->command($socket, "EHLO {$this->heloDomain}", [250]);

        return array_map(static fn (string $line): string => strtoupper(trim(substr($line, 4))), array_slice($lines, 1));
    }

    /**
     * @param resource $socket
     * @param list<string> $capabilities
     * @return list<string>
     */
    private function startTls($socket, array $capabilities): array
    {
        if (! in_array('STARTTLS', $capabilities, true)) {
            throw new MailException("El servidor {$this->host} no ofrece STARTTLS. Usa MAIL_ENCRYPTION=ssl (puerto 465) o none si es un servidor local.");
        }

        $this->command($socket, 'STARTTLS', [220]);

        $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);

        [$enabled, $warning] = self::quietly(static fn (): bool|int => stream_socket_enable_crypto($socket, true, $method));

        if ($enabled !== true) {
            $detail = $warning !== '' ? " ({$warning})" : '';

            throw new MailException("No se pudo cifrar la conexión con {$this->host}{$detail}. Revisa el certificado del servidor o MAIL_VERIFY_PEER.");
        }

        return $this->ehlo($socket);
    }

    /**
     * @param resource $socket
     * @param list<string> $capabilities
     */
    private function authenticate($socket, array $capabilities): void
    {
        $methods = [];
        foreach ($capabilities as $capability) {
            if (str_starts_with($capability, 'AUTH ') || str_starts_with($capability, 'AUTH=')) {
                $methods = [...$methods, ...preg_split('/\s+/', trim(substr($capability, 5))) ?: []];
            }
        }

        $username = (string) $this->username;
        $password = (string) $this->password;

        if (in_array('PLAIN', $methods, true)) {
            $this->command($socket, 'AUTH PLAIN ' . base64_encode("\0{$username}\0{$password}"), [235], 'autenticación');

            return;
        }

        if (in_array('LOGIN', $methods, true)) {
            $this->command($socket, 'AUTH LOGIN', [334], 'autenticación');
            $this->command($socket, base64_encode($username), [334], 'autenticación');
            $this->command($socket, base64_encode($password), [235], 'autenticación');

            return;
        }

        throw new MailException("El servidor {$this->host} no acepta AUTH PLAIN ni LOGIN" . ($this->encryption === 'none' ? ' sin cifrado: prueba MAIL_ENCRYPTION=tls.' : '.'));
    }

    /**
     * Envía un comando y valida el código de la respuesta.
     *
     * @param resource $socket
     * @param list<int> $codes
     * @param string|null $step Nombre del paso en el error, en lugar del comando (para no mostrar credenciales)
     * @return list<string> Líneas de la respuesta
     */
    private function command($socket, string $command, array $codes, ?string $step = null): array
    {
        $this->write($socket, $command . "\r\n");

        return $this->expect($socket, $codes, $step ?? strtok($command, ':<') ?: $command);
    }

    /**
     * @param resource $socket
     * @param list<int> $codes
     * @return list<string>
     */
    private function expect($socket, array $codes, string $step): array
    {
        $lines = [];

        do {
            $line = fgets($socket, 1024);

            if ($line === false) {
                $timedOut = stream_get_meta_data($socket)['timed_out'];

                throw new MailException($timedOut
                    ? "El servidor SMTP {$this->host} no respondió a tiempo ({$step})."
                    : "El servidor SMTP {$this->host} cerró la conexión ({$step}).");
            }

            $lines[] = rtrim($line, "\r\n");
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($lines[0], 0, 3);

        if (! in_array($code, $codes, true)) {
            $hint = $code === 535 ? ' Revisa MAIL_USERNAME y MAIL_PASSWORD.' : '';

            throw new MailException("El servidor SMTP rechazó {$step}: " . implode(' ', $lines) . $hint);
        }

        return $lines;
    }

    /**
     * Ejecuta una función de sockets sin que sus avisos de PHP salgan en pantalla y devuelve el último
     * aviso como texto, para incluirlo en el error.
     *
     * @template T
     * @param Closure(): T $callback
     * @return array{T, string}
     */
    private static function quietly(Closure $callback): array
    {
        $warning = '';
        set_error_handler(static function (int $level, string $message) use (&$warning): bool {
            $warning = trim((string) preg_replace('/^[a-z_]+\(\): /', '', $message));

            return true;
        });

        try {
            return [$callback(), $warning];
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param resource $socket
     */
    private function write($socket, string $data): void
    {
        $length = strlen($data);

        for ($written = 0; $written < $length; $written += $bytes) {
            $bytes = fwrite($socket, substr($data, $written));

            if ($bytes === false || $bytes === 0) {
                throw new MailException("Se cortó la conexión con el servidor SMTP {$this->host}.");
            }
        }
    }
}
