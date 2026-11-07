<?php

/**
 * FrameworkUpdater.php
 *
 * bin/bootzen framework:update: reemplaza framework/ del proyecto por la versión de la instalación local
 * de BootZen (~/.bootzen o BOOTZEN_HOME). Usa framework/MANIFEST.json (hash de cada archivo) para saber
 * si alguien editó framework/ y no pisar esos cambios sin --force, y deja un respaldo en storage/backups.
 *
 * @package    BootZen\Deploy
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Deploy;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Sin dependencias del resto del framework: bin/bootzen-new lo usa para escribir el manifiesto.
 *
 * @example $resultado = (new FrameworkUpdater('/ruta/proyecto'))->update(getenv('HOME') . '/.bootzen');
 */
final class FrameworkUpdater
{
    public const MANIFEST = 'MANIFEST.json';

    public function __construct(private readonly string $projectPath)
    {
    }

    /**
     * Escribe framework/MANIFEST.json con la versión y el hash de cada archivo.
     */
    public static function writeManifest(string $frameworkPath, string $version): void
    {
        $manifest = ['version' => $version, 'files' => self::hashes($frameworkPath)];

        file_put_contents(
            $frameworkPath . '/' . self::MANIFEST,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );
    }

    /**
     * Versión del framework del proyecto (framework/VERSION).
     */
    public function currentVersion(): string
    {
        return self::versionOf($this->projectPath . '/framework');
    }

    /**
     * Archivos de framework/ que cambiaron desde la última instalación o actualización,
     * o null si no hay manifiesto para comparar.
     *
     * @return list<string>|null
     */
    public function localChanges(): ?array
    {
        $framework = $this->projectPath . '/framework';
        $file = $framework . '/' . self::MANIFEST;
        $manifest = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (! is_array($manifest) || ! is_array($manifest['files'] ?? null)) {
            return null;
        }

        $current = self::hashes($framework);
        $changes = [];

        foreach ($manifest['files'] as $path => $hash) {
            if (($current[$path] ?? null) !== $hash) {
                $changes[] = (string) $path;
            }
        }

        foreach (array_keys($current) as $path) {
            if (! array_key_exists($path, $manifest['files'])) {
                $changes[] = $path;
            }
        }

        sort($changes);

        return $changes;
    }

    /**
     * Reemplaza framework/ por el de $source. Devuelve [versión anterior, versión nueva, respaldo];
     * el respaldo es null si ya estaba al día.
     *
     * @return array{from: string, to: string, backup: string|null}
     * @throws RuntimeException Si el origen no es válido, si es una versión anterior o si hay cambios locales (sin $force)
     */
    public function update(string $source, bool $force = false): array
    {
        $source = rtrim($source, '/');

        if (! is_file($source . '/framework/bootstrap.php') || ! is_file($source . '/VERSION')) {
            throw new RuntimeException("No hay una instalación de BootZen en {$source}. Instálala con install.sh o indica otra con --from=ruta.");
        }

        $from = $this->currentVersion();
        $to = trim((string) file_get_contents($source . '/VERSION'));

        if ($from === $to && ! $force) {
            return ['from' => $from, 'to' => $to, 'backup' => null];
        }

        if (version_compare($to, $from, '<') && ! $force) {
            throw new RuntimeException("La versión de {$source} ({$to}) es anterior a la del proyecto ({$from}). Usa --force para volver atrás.");
        }

        $changes = $this->localChanges();

        if ($changes !== null && $changes !== [] && ! $force) {
            throw new RuntimeException(
                "Hay cambios locales en framework/ que se perderían:\n  - " . implode("\n  - ", array_slice($changes, 0, 20))
                . (count($changes) > 20 ? "\n  - ... y " . (count($changes) - 20) . ' más' : '')
                . "\nMuévelos a app/ o usa --force (queda un respaldo en storage/backups)."
            );
        }

        $framework = $this->projectPath . '/framework';
        $backup = $this->projectPath . '/storage/backups/framework-' . $from . '-' . date('Ymd-His');

        self::copyDirectory($framework, $backup);
        self::removeDirectory($framework);
        self::copyDirectory($source . '/framework', $framework);

        file_put_contents($framework . '/VERSION', $to . "\n");
        self::writeManifest($framework, $to);

        return ['from' => $from, 'to' => $to, 'backup' => substr($backup, strlen($this->projectPath) + 1)];
    }

    private static function versionOf(string $framework): string
    {
        $file = $framework . '/VERSION';

        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    /**
     * @return array<string, string> Ruta relativa => sha256
     */
    private static function hashes(string $directory): array
    {
        $hashes = [];

        foreach (self::files($directory) as $relative => $path) {
            if ($relative !== self::MANIFEST) {
                $hashes[$relative] = (string) hash_file('sha256', $path);
            }
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @return array<string, string> Ruta relativa => ruta absoluta
     */
    private static function files(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            if ($item->isFile()) {
                $files[str_replace('\\', '/', substr($item->getPathname(), strlen($directory) + 1))] = $item->getPathname();
            }
        }

        return $files;
    }

    private static function copyDirectory(string $from, string $to): void
    {
        foreach (self::files($from) as $relative => $path) {
            $target = $to . '/' . $relative;

            if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0775, true) && ! is_dir(dirname($target))) {
                throw new RuntimeException('No se pudo crear la carpeta ' . dirname($target) . '.');
            }

            if (! copy($path, $target)) {
                throw new RuntimeException("No se pudo copiar {$relative}.");
            }
        }
    }

    private static function removeDirectory(string $directory): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
