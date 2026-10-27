<?php

/**
 * Tailwind.php
 *
 * Integración con el CLI standalone de Tailwind CSS v4: descarga el binario de la plataforma,
 * verifica su SHA-256 contra el archivo oficial de sumas y arma los comandos de compilación.
 * No requiere Node. Si el proyecto tiene node_modules/.bin/tailwindcss, se usa ese en su lugar.
 *
 * @package    BootZen\Frontend
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Frontend;

use Closure;
use RuntimeException;

/**
 * CLI standalone de Tailwind CSS.
 */
final class Tailwind
{
    /**
     * Versión del CLI que usa BootZen. Al cambiarla, regenera public/assets/app.css del skeleton.
     */
    public const VERSION = 'v4.3.3';

    private const RELEASES = 'https://github.com/tailwindlabs/tailwindcss/releases/download';

    /**
     * Descarga una URL a un archivo (inyectable en tests).
     *
     * @var Closure(string, string): void
     */
    private readonly Closure $download;

    /**
     * @param string $basePath Raíz del proyecto
     * @param string $os Sistema operativo (PHP_OS_FAMILY)
     * @param string $machine Arquitectura (php_uname('m'))
     * @param bool $musl Linux con musl (Alpine)
     * @param (Closure(string, string): void)|null $download Descargador (url, archivo destino)
     */
    public function __construct(
        private readonly string $basePath,
        private readonly string $os = PHP_OS_FAMILY,
        private readonly string $machine = '',
        private readonly bool $musl = false,
        ?Closure $download = null,
        private readonly string $version = self::VERSION
    ) {
        $this->download = $download ?? self::defaultDownloader(...);
    }

    /**
     * Crea la integración detectando la plataforma actual.
     */
    public static function forCurrentPlatform(string $basePath): self
    {
        return new self($basePath, PHP_OS_FAMILY, php_uname('m'), is_file('/etc/alpine-release'));
    }

    /**
     * Nombre del binario publicado para la plataforma (ej. tailwindcss-linux-x64).
     *
     * @throws RuntimeException Si la plataforma no tiene binario oficial
     */
    public function assetName(): string
    {
        $arch = match (strtolower($this->machine)) {
            'x86_64', 'amd64', 'x64' => 'x64',
            'aarch64', 'arm64' => 'arm64',
            default => throw new RuntimeException(
                "Tailwind no publica binario para la arquitectura [{$this->machine}]. Usa npm: npm i -D @tailwindcss/cli."
            ),
        };

        return match ($this->os) {
            'Linux' => "tailwindcss-linux-{$arch}" . ($this->musl ? '-musl' : ''),
            'Darwin' => "tailwindcss-macos-{$arch}",
            'Windows' => $arch === 'x64'
                ? 'tailwindcss-windows-x64.exe'
                : throw new RuntimeException('Tailwind no publica binario para Windows ARM. Usa npm: npm i -D @tailwindcss/cli.'),
            default => throw new RuntimeException("Sistema operativo [{$this->os}] sin binario de Tailwind. Usa npm."),
        };
    }

    /**
     * Ruta del ejecutable a usar: el de node_modules si existe, si no el binario standalone.
     */
    public function executable(): string
    {
        $node = $this->basePath . '/node_modules/.bin/tailwindcss' . ($this->os === 'Windows' ? '.cmd' : '');

        return is_file($node) ? $node : $this->binaryPath();
    }

    /**
     * Ruta del binario standalone (storage/bin/tailwindcss-<versión>).
     */
    public function binaryPath(): string
    {
        return $this->basePath . '/storage/bin/tailwindcss-' . $this->version . ($this->os === 'Windows' ? '.exe' : '');
    }

    /**
     * Indica si hay un ejecutable listo para usar.
     */
    public function isInstalled(): bool
    {
        return is_file($this->executable());
    }

    /**
     * Descarga el binario de la plataforma y verifica su SHA-256 contra sha256sums.txt del release.
     *
     * @return string Ruta del binario instalado
     * @throws RuntimeException Si la descarga falla o el checksum no coincide
     */
    public function install(): string
    {
        $asset = $this->assetName();
        $base = self::RELEASES . '/' . $this->version;
        $path = $this->binaryPath();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear {$directory}.");
        }

        // El binario pesa más de 100 MB: se descarga a disco y se verifica desde ahí, nunca en memoria
        $sumsFile = $path . '.sha256sums';
        $temporary = $path . '.download';

        try {
            ($this->download)("{$base}/sha256sums.txt", $sumsFile);
            $sums = (string) file_get_contents($sumsFile);

            if (preg_match('/^([a-f0-9]{64})\s+(?:\.\/)?' . preg_quote($asset, '/') . '$/m', $sums, $match) !== 1) {
                throw new RuntimeException("El release {$this->version} de Tailwind no incluye el checksum de {$asset}.");
            }

            ($this->download)("{$base}/{$asset}", $temporary);

            if (! hash_equals($match[1], (string) hash_file('sha256', $temporary))) {
                throw new RuntimeException(
                    "El SHA-256 de {$asset} no coincide con el publicado por Tailwind. No se instaló el binario."
                );
            }

            if (! rename($temporary, $path) || ! chmod($path, 0755)) {
                throw new RuntimeException("No se pudo guardar el binario en {$path}.");
            }
        } finally {
            foreach ([$sumsFile, $temporary] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        return $path;
    }

    /**
     * Argumentos para compilar resources/css/app.css a public/assets/app.css.
     *
     * @return list<string>
     */
    public function buildArguments(bool $watch = false): array
    {
        return [
            $this->executable(),
            '--input',
            $this->basePath . '/resources/css/app.css',
            '--output',
            $this->basePath . '/public/assets/app.css',
            $watch ? '--watch' : '--minify',
        ];
    }

    /**
     * Descarga una URL directo a un archivo, sin cargarla en memoria.
     *
     * @throws RuntimeException Si la descarga falla
     */
    private static function defaultDownloader(string $url, string $destination): void
    {
        $context = stream_context_create(['http' => [
            'follow_location' => 1,
            'max_redirects' => 10,
            'timeout' => 120,
            'user_agent' => 'BootZen',
        ]]);

        if (! @copy($url, $destination, $context) || (int) filesize($destination) === 0) {
            throw new RuntimeException(
                "No se pudo descargar {$url}. Revisa la conexión o descarga el archivo a mano en storage/bin/."
            );
        }
    }
}
