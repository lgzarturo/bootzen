<?php

/**
 * Env.php
 *
 * Cargador de archivos .env sin dependencias externas.
 *
 * Formato soportado:
 * - `CLAVE=valor`, con `export ` opcional al inicio.
 * - Comentarios de línea (`# ...`) y comentarios al final de valores sin comillas (` # ...`).
 * - Comillas simples (valor literal) y dobles (admiten \n, \t, \" y ${OTRA} para interpolar).
 *
 * Las variables que ya existen en el entorno real (servidor, Docker) nunca se sobrescriben.
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
 * Lee un archivo .env y publica sus valores en $_ENV, $_SERVER y getenv().
 *
 * @example Env::load(base_path('.env'));
 */
final class Env
{
    /**
     * Carga el archivo si existe. Devuelve las variables leídas del archivo.
     *
     * @param string $file Ruta al archivo .env
     * @return array<string, string> Variables definidas en el archivo
     * @throws RuntimeException Si una línea no tiene un formato válido
     */
    public static function load(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("No se pudo leer el archivo de entorno {$file}.");
        }

        $values = self::parse($contents);

        foreach ($values as $key => $value) {
            if (self::exists($key)) {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        return $values;
    }

    /**
     * Interpreta el contenido de un archivo .env.
     *
     * @param string $contents Contenido del archivo
     * @return array<string, string> Variables en orden de aparición
     * @throws RuntimeException Si una línea no tiene un formato válido
     */
    public static function parse(string $contents): array
    {
        $values = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        foreach ($lines as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*)\s*=\s*(.*)$/', $line, $matches) !== 1) {
                throw new RuntimeException(sprintf('Línea %d del .env inválida: "%s".', $number + 1, $line));
            }

            $values[$matches[1]] = self::parseValue($matches[2], $values, $number + 1);
        }

        return $values;
    }

    /**
     * Interpreta el valor de una variable según sus comillas.
     *
     * @param array<string, string> $defined Variables ya leídas, para interpolar
     */
    private static function parseValue(string $raw, array $defined, int $line): string
    {
        if ($raw === '') {
            return '';
        }

        $quote = $raw[0];

        if ($quote === "'" || $quote === '"') {
            $end = self::closingQuote($raw, $quote);
            if ($end === null) {
                throw new RuntimeException("Línea {$line} del .env: falta cerrar la comilla {$quote}.");
            }

            $value = substr($raw, 1, $end - 1);

            return $quote === "'" ? $value : self::expand(stripcslashes($value), $defined);
        }

        $commentAt = strpos($raw, ' #');
        $value = trim($commentAt === false ? $raw : substr($raw, 0, $commentAt));

        return self::expand($value, $defined);
    }

    /**
     * Busca la posición de la comilla de cierre, ignorando las escapadas.
     */
    private static function closingQuote(string $raw, string $quote): ?int
    {
        $length = strlen($raw);

        for ($i = 1; $i < $length; $i++) {
            if ($raw[$i] === '\\' && $quote === '"') {
                $i++;

                continue;
            }

            if ($raw[$i] === $quote) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Reemplaza ${VARIABLE} por su valor del archivo o del entorno.
     *
     * @param array<string, string> $defined
     */
    private static function expand(string $value, array $defined): string
    {
        return (string) preg_replace_callback(
            '/\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/',
            static function (array $match) use ($defined): string {
                if (array_key_exists($match[1], $defined)) {
                    return $defined[$match[1]];
                }

                $fromEnvironment = $_ENV[$match[1]] ?? getenv($match[1]);

                return is_scalar($fromEnvironment) ? (string) $fromEnvironment : '';
            },
            $value
        );
    }

    /**
     * Indica si la variable ya existe en el entorno real.
     */
    private static function exists(string $key): bool
    {
        return array_key_exists($key, $_ENV) || getenv($key) !== false;
    }
}
