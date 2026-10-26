<?php

/**
 * Config.php
 *
 * Configuración de la aplicación a partir de archivos PHP en config/ que retornan arrays.
 * Cada archivo se expone con su nombre como primer segmento: config/app.php → config('app.name').
 *
 * En producción la configuración completa se puede guardar en un solo archivo cacheado
 * (storage/cache/config.php) para no leer cada archivo en cada petición.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use RuntimeException;

/**
 * Repositorio de configuración con acceso por notación de puntos.
 *
 * @example $config->get('app.timezone', 'UTC');
 */
final class Config
{
    /**
     * @param array<string, mixed> $items Configuración completa
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * Carga todos los archivos *.php de un directorio.
     *
     * @param string $directory Directorio config/
     * @throws RuntimeException Si un archivo no retorna un array
     */
    public static function fromDirectory(string $directory): self
    {
        $items = [];

        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            $values = require $file;
            if (! is_array($values)) {
                throw new RuntimeException("El archivo de configuración {$file} debe retornar un array.");
            }

            $items[basename($file, '.php')] = $values;
        }

        return new self($items);
    }

    /**
     * Carga la configuración desde la caché si existe; si no, desde el directorio.
     *
     * @param string $directory Directorio config/
     * @param string $cacheFile Archivo de caché generado con writeCache()
     */
    public static function load(string $directory, string $cacheFile): self
    {
        if (is_file($cacheFile)) {
            $items = require $cacheFile;
            if (is_array($items)) {
                return new self($items);
            }
        }

        return self::fromDirectory($directory);
    }

    /**
     * Obtiene un valor por notación de puntos.
     *
     * @param string $key Clave, por ejemplo 'app.name'
     * @param mixed $default Valor si la clave no existe
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Indica si una clave existe.
     */
    public function has(string $key): bool
    {
        $missing = new \stdClass();

        return $this->get($key, $missing) !== $missing;
    }

    /**
     * Asigna un valor en tiempo de ejecución (no se persiste).
     */
    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $items = &$this->items;

        foreach (array_slice($segments, 0, -1) as $segment) {
            if (! isset($items[$segment]) || ! is_array($items[$segment])) {
                $items[$segment] = [];
            }

            $items = &$items[$segment];
        }

        $items[end($segments)] = $value;
    }

    /**
     * Devuelve toda la configuración.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Guarda la configuración actual en un solo archivo PHP para producción.
     *
     * Los valores deben ser exportables con var_export (sin closures ni objetos).
     *
     * @param string $cacheFile Ruta del archivo a generar
     * @throws RuntimeException Si no se puede escribir el archivo
     */
    public function writeCache(string $cacheFile): void
    {
        $directory = dirname($cacheFile);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio {$directory}.");
        }

        $contents = "<?php\n\n// Generado por BootZen. No editar: se regenera desde config/.\n\nreturn "
            . var_export($this->items, true) . ";\n";

        $temporary = $cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $cacheFile)) {
            throw new RuntimeException("No se pudo escribir la caché de configuración en {$cacheFile}.");
        }
    }
}
