<?php

/**
 * Servidor SMTP falso para los tests de SmtpMailer. Atiende una conexión y termina.
 *
 * Uso: php fake-smtp-server.php '<json>' con las opciones:
 *   log         archivo donde anota cada línea recibida (JSON por línea) y el mensaje de DATA
 *   caps        capacidades que anuncia en EHLO (por ejemplo ["STARTTLS", "AUTH PLAIN LOGIN"])
 *   cert        certificado PEM (con la llave) para STARTTLS o TLS directo
 *   implicit    true para TLS desde el inicio (como el puerto 465)
 *   auth        "ok" o "fail"
 *   reject      destinatarios que responde con 550
 *   greeting    código del saludo (220 por defecto)
 *   silent      true para no responder nunca (prueba de tiempo de espera)
 *
 * Escribe en la salida el puerto elegido y después atiende.
 */

declare(strict_types=1);

$options = json_decode($argv[1] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
$log = (string) $options['log'];
$cert = $options['cert'] ?? null;
$context = stream_context_create(['ssl' => $cert === null ? [] : [
    'local_cert' => $cert,
    'verify_peer' => false,
]]);

$scheme = ($options['implicit'] ?? false) ? 'ssl' : 'tcp';
$server = stream_socket_server("{$scheme}://127.0.0.1:0", $code, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

if ($server === false) {
    fwrite(STDERR, "No se pudo abrir el puerto: {$error}\n");
    exit(1);
}

$name = (string) stream_socket_get_name($server, false);
echo substr($name, (int) strrpos($name, ':') + 1), "\n";
fflush(STDOUT);

$client = @stream_socket_accept($server, 10);

if ($client === false) {
    exit(0);
}

stream_set_timeout($client, 10);

$record = static function (string $type, string $value) use ($log): void {
    file_put_contents($log, json_encode(['type' => $type, 'value' => $value]) . "\n", FILE_APPEND);
};

$reply = static function (string $text) use ($client): void {
    fwrite($client, $text . "\r\n");
};

if ($options['silent'] ?? false) {
    sleep(5);
    exit(0);
}

$reply(($options['greeting'] ?? 220) . ' fake.test ESMTP');

while (($line = fgets($client)) !== false) {
    $line = rtrim($line, "\r\n");
    $record('command', $line);
    $verb = strtoupper((string) strtok($line, ' :'));

    switch ($verb) {
        case 'EHLO':
            $caps = $options['caps'] ?? [];
            $reply(($caps === [] ? '250 ' : '250-') . 'fake.test');
            foreach (array_values($caps) as $i => $cap) {
                $reply(($i === count($caps) - 1 ? '250 ' : '250-') . $cap);
            }

            break;

        case 'STARTTLS':
            $reply('220 listo para TLS');
            stream_context_set_option($client, 'ssl', 'local_cert', (string) $cert);
            stream_context_set_option($client, 'ssl', 'verify_peer', false);
            if (stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
                $record('error', 'tls');
                exit(1);
            }
            $record('tls', 'on');

            break;

        case 'AUTH':
            $ok = ($options['auth'] ?? 'ok') === 'ok';
            if (str_starts_with(strtoupper($line), 'AUTH LOGIN')) {
                $reply('334 VXNlcm5hbWU6');
                $record('login-user', base64_decode(rtrim((string) fgets($client), "\r\n")));
                $reply('334 UGFzc3dvcmQ6');
                $record('login-password', base64_decode(rtrim((string) fgets($client), "\r\n")));
            }
            $reply($ok ? '235 autenticado' : '535 5.7.8 credenciales inválidas');

            break;

        case 'MAIL':
            $reply('250 ok');

            break;

        case 'RCPT':
            preg_match('/<([^>]*)>/', $line, $match);
            $reply(in_array($match[1] ?? '', $options['reject'] ?? [], true) ? '550 5.1.1 buzón inexistente' : '250 ok');

            break;

        case 'DATA':
            $reply('354 termina con un punto');
            $data = '';
            while (($chunk = fgets($client)) !== false && $chunk !== ".\r\n") {
                $data .= $chunk;
            }
            $record('data', $data);
            $reply('250 encolado');

            break;

        case 'QUIT':
            $reply('221 adiós');
            exit(0);

        default:
            $reply('502 comando no implementado');
    }
}
