<?php

declare(strict_types=1);

/**
 * Integración con servidores FTP/FTPS y SFTP reales. Cada bloque se ejecuta solo si existe su
 * variable (CI las define): BOOTZEN_TEST_FTP_HOST, _PORT, _USERNAME, _PASSWORD (FTPS) y
 * BOOTZEN_TEST_SFTP_HOST, _PORT, _USERNAME, _PASSWORD, _ROOT.
 */

use BootZen\Storage\FtpDisk;
use BootZen\Storage\SftpDisk;
use BootZen\Storage\StorageException;
use BootZen\Storage\StorageManager;

function remoteEnv(string $key, string $default = ''): string
{
    $value = getenv('BOOTZEN_TEST_' . $key);

    return $value === false ? $default : $value;
}

/**
 * Ciclo completo sobre un disco: guarda en carpetas nuevas, lee, mide, sobrescribe y borra.
 */
function remoteRoundTrip(BootZen\Storage\Storage $disk): void
{
    $content = '%PDF-1.4 ' . random_bytes(20000);
    $path = 'pruebas/' . bin2hex(random_bytes(4)) . '/Factura ñ.pdf';

    $disk->put($path, $content);

    expect($disk->exists($path))->toBeTrue()
        ->and($disk->get($path))->toBe($content)
        ->and($disk->size($path))->toBe(strlen($content));

    $disk->put($path, 'versión 2');

    expect($disk->get($path))->toBe('versión 2')
        ->and($disk->delete($path))->toBeTrue()
        ->and($disk->delete($path))->toBeFalse()
        ->and($disk->exists($path))->toBeFalse()
        ->and(fn () => $disk->get($path))->toThrow(StorageException::class, 'No existe el archivo')
        ->and(fn () => $disk->size($path))->toThrow(StorageException::class, 'No existe el archivo');
}

it('guarda y entrega por FTPS', function (): void {
    if (remoteEnv('FTP_HOST') === '') {
        $this->markTestSkipped('FTP no configurado (BOOTZEN_TEST_FTP_HOST).');
    }

    $manager = new StorageManager(['disks' => ['ftp' => [
        'driver' => 'ftp',
        'host' => remoteEnv('FTP_HOST'),
        'port' => remoteEnv('FTP_PORT', '21'),
        'username' => remoteEnv('FTP_USERNAME'),
        'password' => remoteEnv('FTP_PASSWORD'),
        'ssl' => true,
        'root' => 'archivos',
    ]]], sys_get_temp_dir());

    remoteRoundTrip($manager->disk('ftp'));

    $bad = new FtpDisk(remoteEnv('FTP_HOST'), remoteEnv('FTP_USERNAME'), 'otra', (int) remoteEnv('FTP_PORT', '21'), ssl: true);

    expect(fn () => $bad->exists('a.txt'))->toThrow(StorageException::class, 'rechazó el usuario o la contraseña')
        ->and(fn () => (new FtpDisk('127.0.0.1', 'u', 'p', 1, timeout: 2))->exists('a.txt'))->toThrow(StorageException::class, 'No se pudo conectar al FTP 127.0.0.1:1');
});

it('guarda y entrega por SFTP y verifica la huella del servidor', function (): void {
    if (remoteEnv('SFTP_HOST') === '') {
        $this->markTestSkipped('SFTP no configurado (BOOTZEN_TEST_SFTP_HOST).');
    }

    $config = [
        'driver' => 'sftp',
        'host' => remoteEnv('SFTP_HOST'),
        'port' => remoteEnv('SFTP_PORT', '22'),
        'username' => remoteEnv('SFTP_USERNAME'),
        'password' => remoteEnv('SFTP_PASSWORD'),
        'root' => remoteEnv('SFTP_ROOT', '/'),
    ];
    $manager = new StorageManager(['disks' => ['sftp' => $config]], sys_get_temp_dir());

    remoteRoundTrip($manager->disk('sftp'));

    $wrongPrint = new StorageManager(['disks' => ['sftp' => $config + ['fingerprint' => str_repeat('ab', 20)]]], sys_get_temp_dir());
    $bad = new SftpDisk(remoteEnv('SFTP_HOST'), remoteEnv('SFTP_USERNAME'), 'otra', port: (int) remoteEnv('SFTP_PORT', '22'));

    expect(fn () => $wrongPrint->disk('sftp')->exists('a.txt'))->toThrow(StorageException::class, 'no coincide con SFTP_FINGERPRINT')
        ->and(fn () => $bad->exists('a.txt'))->toThrow(StorageException::class, 'rechazó al usuario');

    // Con la huella correcta (la que el error dice) sí entra
    try {
        $wrongPrint->disk('sftp')->exists('a.txt');
    } catch (StorageException $e) {
        preg_match('/el servidor dice ([a-f0-9]+)/', $e->getMessage(), $match);
        $right = new StorageManager(['disks' => ['sftp' => $config + ['fingerprint' => $match[1] ?? '']]], sys_get_temp_dir());

        expect($right->disk('sftp')->exists('no-esta.txt'))->toBeFalse();
    }
});
