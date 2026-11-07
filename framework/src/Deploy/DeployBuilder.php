<?php

/**
 * DeployBuilder.php
 *
 * Arma el artefacto de despliegue (bin/bootzen deploy:build): una copia del proyecto sin lo que solo
 * sirve en desarrollo, con las vistas precompiladas, en build/<app>-<versión>/ y comprimida en .zip
 * (o .tar.gz si PHP no trae la extensión zip). Sirve igual para hosting compartido, VPS y Docker.
 *
 * @package    BootZen\Deploy
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Deploy;

use BootZen\View\View;
use FilesystemIterator;
use InvalidArgumentException;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * El artefacto lleva app/, bootstrap/, bin/bootzen, config/, database/ (migraciones y seeders),
 * framework/, public/, resources/views/, routes/ y la estructura de storage/, más .env.example,
 * .htaccess y composer.json. Nunca lleva .env, la base SQLite, tests/, vendor/ ni archivos subidos.
 *
 * @example $builder->build('tienda', '1.2.0'); // dir, archive, files y views
 */
final class DeployBuilder
{
    /**
     * Rutas que se copian (carpetas o archivos), relativas al proyecto.
     */
    private const INCLUDE = [
        'app', 'bootstrap', 'bin/bootzen', 'config', 'database/migrations', 'database/seeds', 'framework',
        'public', 'resources/views', 'routes', 'storage', '.env.example', '.htaccess', 'composer.json',
    ];

    /**
     * Patrones (fnmatch sobre la ruta relativa) que nunca entran al artefacto.
     */
    private const EXCLUDE = [
        '.env', '*.sqlite', '*.sqlite-*', '*.log', '.DS_Store',
        // De storage/ y public/uploads/ solo viaja la estructura: .gitignore y .htaccess
        'storage/*/*', 'public/uploads/*',
    ];

    private const KEEP = ['.gitignore', '.htaccess'];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Arma el artefacto y devuelve la carpeta y el archivo comprimido (null si PHP no trae zip ni phar).
     *
     * @return array{dir: string, archive: string|null, files: int, views: int}
     * @throws InvalidArgumentException Si el nombre o la versión no son válidos
     * @throws RuntimeException Si no se puede escribir
     */
    public function build(string $name, string $version): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) !== 1 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $version) !== 1) {
            throw new InvalidArgumentException('El nombre y la versión solo pueden tener letras, números, punto, guion y guion bajo.');
        }

        $release = $name . '-' . $version;
        $target = $this->basePath . '/build/' . $release;

        self::remove($target);
        self::makeDirectory($target);

        $files = 0;
        foreach (self::INCLUDE as $path) {
            $files += $this->copy($path, $target);
        }

        // Vistas precompiladas: el servidor no compila nada en la primera visita
        $views = (new View($target . '/resources/views', $target . '/storage/cache/views'))->compileAll();

        file_put_contents($target . '/BUILD', "version={$version}\nbuilt_at=" . date('c') . "\n");

        return [
            'dir' => $target,
            'archive' => $this->archive($target, $release),
            'files' => $files + 1,
            'views' => $views,
        ];
    }

    /**
     * Copia un archivo o carpeta respetando las exclusiones. Devuelve cuántos archivos copió.
     */
    private function copy(string $relative, string $target): int
    {
        $source = $this->basePath . '/' . $relative;

        if (is_file($source)) {
            return $this->copyFile($source, $target . '/' . $relative, $relative) ? 1 : 0;
        }

        if (! is_dir($source)) {
            return 0;
        }

        $count = 0;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            $path = $relative . '/' . substr($item->getPathname(), strlen($source) + 1);

            if ($item->isFile() && $this->copyFile($item->getPathname(), $target . '/' . $path, $path)) {
                $count++;
            }
        }

        return $count;
    }

    private function copyFile(string $from, string $to, string $relative): bool
    {
        if ($this->excluded($relative)) {
            return false;
        }

        self::makeDirectory(dirname($to));

        if (! copy($from, $to)) {
            throw new RuntimeException("No se pudo copiar {$relative}.");
        }

        $permissions = fileperms($from);
        if ($permissions !== false) {
            chmod($to, $permissions & 0777);
        }

        // Conserva la fecha: las vistas precompiladas dependen de que el original no parezca más nuevo
        touch($to, (int) filemtime($from));

        return true;
    }

    private function excluded(string $relative): bool
    {
        if (in_array(basename($relative), self::KEEP, true)) {
            return false;
        }

        foreach (self::EXCLUDE as $pattern) {
            if (fnmatch($pattern, $relative) || fnmatch($pattern, basename($relative))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Comprime la carpeta en .zip (con permisos, para que bin/bootzen siga siendo ejecutable) o en .tar.gz.
     */
    private function archive(string $directory, string $release): ?string
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        if (class_exists(ZipArchive::class)) {
            $file = dirname($directory) . '/' . $release . '.zip';
            self::removeFile($file);

            $zip = new ZipArchive();
            if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("No se pudo crear {$file}.");
            }

            /** @var SplFileInfo $item */
            foreach ($items as $item) {
                $name = $release . '/' . substr($item->getPathname(), strlen($directory) + 1);
                $zip->addFile($item->getPathname(), $name);
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, ((int) fileperms($item->getPathname()) & 0xFFFF) << 16);
                $zip->setMtimeName($name, (int) filemtime($item->getPathname()));
            }

            $zip->close();

            return $file;
        }

        if (! class_exists(PharData::class)) {
            return null;
        }

        // PharData no guarda fechas: en el servidor las vistas se vuelven a compilar en la primera visita

        $tar = dirname($directory) . '/' . $release . '.tar';
        self::removeFile($tar);
        self::removeFile($tar . '.gz');

        $phar = new PharData($tar);
        $phar->buildFromDirectory(dirname($directory), '#^' . preg_quote($directory, '#') . '/#');
        $phar->compress(\Phar::GZ);
        unset($phar);
        self::removeFile($tar);

        return $tar . '.gz';
    }

    private static function makeDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear la carpeta {$directory}.");
        }
    }

    private static function removeFile(string $file): void
    {
        if (is_file($file)) {
            unlink($file);
        }
    }

    private static function remove(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

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
