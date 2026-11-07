<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Mail\Mailer;
use BootZen\Mail\MailException;
use BootZen\Mail\Message;
use BootZen\Mail\SmtpMailer;

/**
 * Levanta tests/Fixtures/fake-smtp-server.php y devuelve su puerto y una función para leer lo que recibió.
 *
 * @param array<string, mixed> $options
 * @return array{port: int, read: Closure(): list<array{type: string, value: string}>, stop: Closure(): void}
 */
function fakeSmtp(array $options = []): array
{
    $log = tempnam(sys_get_temp_dir(), 'bootzen-smtp');
    $process = proc_open(
        [PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/fake-smtp-server.php', json_encode(['log' => $log, ...$options], JSON_THROW_ON_ERROR)],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    if (! is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar el servidor SMTP falso.');
    }

    $port = (int) trim((string) fgets($pipes[1]));

    return [
        'port' => $port,
        'read' => static function () use ($log): array {
            $lines = file($log, FILE_IGNORE_NEW_LINES) ?: [];

            return array_map(static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
        },
        'stop' => static function () use ($process, $pipes, $log): void {
            proc_terminate($process);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            @unlink($log);
        },
    ];
}

/**
 * Certificado autofirmado para 127.0.0.1 en un archivo PEM (certificado y llave).
 */
function selfSignedCertificate(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => '127.0.0.1'], $key);
    $cert = openssl_csr_sign($csr, null, $key, 1);
    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem);

    $file = tempnam(sys_get_temp_dir(), 'bootzen-cert');
    file_put_contents($file, $certPem . $keyPem);

    return $file;
}

/**
 * @param list<array{type: string, value: string}> $log
 * @return list<string>
 */
function smtpCommands(array $log): array
{
    return array_values(array_map(
        static fn (array $entry): string => $entry['value'],
        array_filter($log, static fn (array $entry): bool => $entry['type'] === 'command')
    ));
}

/**
 * @param list<array{type: string, value: string}> $log
 */
function smtpData(array $log): string
{
    foreach ($log as $entry) {
        if ($entry['type'] === 'data') {
            return $entry['value'];
        }
    }

    return '';
}

it('envía sin cifrar: sobre con todos los destinatarios y punto duplicado', function (): void {
    $server = fakeSmtp();

    try {
        (new SmtpMailer('127.0.0.1', $server['port'], 'none', fromAddress: 'ventas@tienda.mx', fromName: 'Tienda', heloDomain: 'tienda.mx'))
            ->send((new Message('ana@ejemplo.com', 'Hola', "Primera línea\n.\nÚltima"))->cc('luis@ejemplo.com')->bcc('oculto@ejemplo.com'));

        $log = ($server['read'])();
    } finally {
        ($server['stop'])();
    }

    expect(smtpCommands($log))->toBe([
        'EHLO tienda.mx',
        'MAIL FROM:<ventas@tienda.mx>',
        'RCPT TO:<ana@ejemplo.com>',
        'RCPT TO:<luis@ejemplo.com>',
        'RCPT TO:<oculto@ejemplo.com>',
        'DATA',
        'QUIT',
    ])
        ->and(smtpData($log))->toContain("\r\n..\r\n")
        ->toContain('From: "Tienda" <ventas@tienda.mx>')
        ->not->toContain('oculto@ejemplo.com');
});

it('usa STARTTLS y AUTH PLAIN', function (): void {
    $cert = selfSignedCertificate();
    $server = fakeSmtp(['cert' => $cert, 'caps' => ['STARTTLS', 'AUTH PLAIN LOGIN']]);

    try {
        (new SmtpMailer('127.0.0.1', $server['port'], 'tls', 'usuario', 'clave-secreta', 'ventas@tienda.mx', verifyPeer: false))
            ->send(new Message('ana@ejemplo.com', 'Hola', 'Texto'));
        $log = ($server['read'])();
    } finally {
        ($server['stop'])();
        unlink($cert);
    }

    $commands = smtpCommands($log);

    expect(array_slice($commands, 0, 3))->toBe(['EHLO localhost', 'STARTTLS', 'EHLO localhost'])
        ->and($log)->toContain(['type' => 'tls', 'value' => 'on'])
        ->and($commands[3])->toBe('AUTH PLAIN ' . base64_encode("\0usuario\0clave-secreta"))
        ->and(smtpData($log))->toContain('Subject: Hola');
});

it('usa TLS desde el inicio y AUTH LOGIN si no hay PLAIN', function (): void {
    $cert = selfSignedCertificate();
    $server = fakeSmtp(['cert' => $cert, 'implicit' => true, 'caps' => ['AUTH LOGIN']]);

    try {
        (new SmtpMailer('127.0.0.1', $server['port'], 'ssl', 'usuario', 'clave-secreta', 'ventas@tienda.mx', verifyPeer: false))
            ->send(new Message('ana@ejemplo.com', 'Hola', 'Texto'));
        $log = ($server['read'])();
    } finally {
        ($server['stop'])();
        unlink($cert);
    }

    expect($log)->toContain(['type' => 'login-user', 'value' => 'usuario'])
        ->toContain(['type' => 'login-password', 'value' => 'clave-secreta'])
        ->and(smtpCommands($log))->toContain('DATA');
});

it('rechaza un certificado que no se puede verificar', function (): void {
    $cert = selfSignedCertificate();
    $server = fakeSmtp(['cert' => $cert, 'caps' => ['STARTTLS']]);

    try {
        expect(fn () => (new SmtpMailer('127.0.0.1', $server['port'], 'tls', fromAddress: 'ventas@tienda.mx'))->send(new Message('ana@ejemplo.com', 'Hola', 'x')))
            ->toThrow(MailException::class, 'No se pudo cifrar la conexión con 127.0.0.1');
    } finally {
        ($server['stop'])();
        unlink($cert);
    }
});

it('explica los errores del servidor sin mostrar la contraseña', function (array $options, Closure $send, string $error): void {
    $server = fakeSmtp($options);

    try {
        $thrown = null;

        try {
            $send($server['port']);
        } catch (MailException $e) {
            $thrown = $e;
        }
    } finally {
        ($server['stop'])();
    }

    expect($thrown)->toBeInstanceOf(MailException::class)
        ->and($thrown?->getMessage())->toContain($error)
        ->not->toContain('clave-secreta')
        ->not->toContain(base64_encode("\0usuario\0clave-secreta"));
})->with([
    'credenciales' => [
        ['caps' => ['AUTH PLAIN'], 'auth' => 'fail'],
        fn (int $port) => (new SmtpMailer('127.0.0.1', $port, 'none', 'usuario', 'clave-secreta', 'ventas@tienda.mx'))->send(new Message('ana@ejemplo.com', 'Hola', 'x')),
        'El servidor SMTP rechazó autenticación: 535 5.7.8 credenciales inválidas Revisa MAIL_USERNAME y MAIL_PASSWORD.',
    ],
    'destinatario' => [
        ['reject' => ['nadie@ejemplo.com']],
        fn (int $port) => (new SmtpMailer('127.0.0.1', $port, 'none', fromAddress: 'ventas@tienda.mx'))->send(new Message('nadie@ejemplo.com', 'Hola', 'x')),
        'El servidor SMTP rechazó el destinatario nadie@ejemplo.com: 550 5.1.1 buzón inexistente',
    ],
    'sin STARTTLS' => [
        [],
        fn (int $port) => (new SmtpMailer('127.0.0.1', $port, 'tls', fromAddress: 'ventas@tienda.mx'))->send(new Message('ana@ejemplo.com', 'Hola', 'x')),
        'no ofrece STARTTLS',
    ],
    'sin AUTH' => [
        [],
        fn (int $port) => (new SmtpMailer('127.0.0.1', $port, 'none', 'usuario', 'clave-secreta', 'ventas@tienda.mx'))->send(new Message('ana@ejemplo.com', 'Hola', 'x')),
        'no acepta AUTH PLAIN ni LOGIN sin cifrado: prueba MAIL_ENCRYPTION=tls.',
    ],
    'saludo' => [
        ['greeting' => 554],
        fn (int $port) => (new SmtpMailer('127.0.0.1', $port, 'none', fromAddress: 'ventas@tienda.mx'))->send(new Message('ana@ejemplo.com', 'Hola', 'x')),
        'El servidor SMTP rechazó saludo: 554 fake.test ESMTP',
    ],
    'sin respuesta' => [
        ['silent' => true],
        fn (int $port) => (new SmtpMailer('127.0.0.1', $port, 'none', fromAddress: 'ventas@tienda.mx', timeout: 1))->send(new Message('ana@ejemplo.com', 'Hola', 'x')),
        'no respondió a tiempo (saludo)',
    ],
]);

it('no se conecta si el correo no se puede armar o falta el remitente', function (): void {
    $mailer = new SmtpMailer('127.0.0.1', 1, 'none', fromAddress: 'ventas@tienda.mx');

    expect(fn () => $mailer->send(new Message('no-es-correo', 'Hola', 'x')))->toThrow(MailException::class, 'Dirección de correo inválida')
        ->and(fn () => (new SmtpMailer('127.0.0.1', 1, 'none'))->send(new Message('ana@ejemplo.com', 'Hola', 'x')))->toThrow(MailException::class, 'define MAIL_FROM_ADDRESS')
        ->and(fn () => $mailer->send(new Message('ana@ejemplo.com', 'Hola', 'x')))->toThrow(MailException::class, 'No se pudo conectar al servidor SMTP 127.0.0.1:1')
        ->and(fn () => new SmtpMailer('127.0.0.1', 587, 'starttls'))->toThrow(InvalidArgumentException::class, 'MAIL_ENCRYPTION [starttls] inválido');
});

it('respeta el remitente propio del mensaje', function (): void {
    $server = fakeSmtp();

    try {
        (new SmtpMailer('127.0.0.1', $server['port'], 'none', fromAddress: 'hola@tienda.mx'))
            ->send((new Message('ana@ejemplo.com', 'Hola', 'x'))->from('cobranza@tienda.mx', 'Cobranza'));
        $log = ($server['read'])();
    } finally {
        ($server['stop'])();
    }

    expect(smtpCommands($log))->toContain('MAIL FROM:<cobranza@tienda.mx>')
        ->and(smtpData($log))->toContain('From: "Cobranza" <cobranza@tienda.mx>');
});

it('lo arma desde config/mail.php con MAIL_DRIVER=smtp', function (): void {
    $project = makeTempDir('bootzen-smtp-app');
    writeFile($project . '/config/app.php', "<?php return ['url' => 'https://tienda.mx'];");
    writeFile($project . '/config/mail.php', "<?php return ['driver' => 'smtp', 'from' => ['address' => 'hola@tienda.mx', 'name' => 'Tienda'], 'smtp' => ['host' => '127.0.0.1', 'port' => '__PORT__', 'encryption' => 'none', 'username' => '', 'timeout' => '5']];");
    $server = fakeSmtp();
    file_put_contents($project . '/config/mail.php', str_replace('__PORT__', (string) $server['port'], (string) file_get_contents($project . '/config/mail.php')));

    try {
        $mailer = (new Application($project))->make(Mailer::class);
        expect($mailer)->toBeInstanceOf(SmtpMailer::class);
        $mailer->send(new Message('ana@ejemplo.com', 'Hola', 'x'));
        $log = ($server['read'])();
    } finally {
        ($server['stop'])();
        removeDir($project);
    }

    // Usuario vacío: sin AUTH. El dominio del EHLO sale de APP_URL
    expect(smtpCommands($log))->toBe(['EHLO tienda.mx', 'MAIL FROM:<hola@tienda.mx>', 'RCPT TO:<ana@ejemplo.com>', 'DATA', 'QUIT']);
});
