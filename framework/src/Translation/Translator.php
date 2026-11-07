<?php

/**
 * Translator.php
 *
 * Traducción de textos: el texto en el idioma base (español) es la clave. __('Guardar tarea') lo
 * devuelve igual en español y busca la traducción en lang/{idioma}.php (y lang/{idioma}/*.php) para
 * los demás idiomas. Primero carga las traducciones del framework y encima las de la app.
 *
 * @package    BootZen\Translation
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.4.0
 */

declare(strict_types=1);

namespace BootZen\Translation;

use BootZen\Core\Route;
use InvalidArgumentException;

/**
 * Un texto sin traducción se muestra en español: la app nunca enseña una clave cruda.
 *
 * @example __('Hola, :nombre', ['nombre' => $usuario->nombre]); trans_choice('una tarea|:count tareas', $total);
 */
final class Translator
{
    /**
     * Traducciones cargadas por idioma.
     *
     * @var array<string, array<string, string>>
     */
    private array $lines = [];

    private ?Route $route = null;

    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    private string $path = '/';

    /**
     * @param list<string> $locales Idiomas permitidos
     * @param list<string> $paths Carpetas lang/ en orden de prioridad creciente (framework, app)
     * @param string $source Idioma en el que están escritos los textos (la clave)
     */
    public function __construct(
        private string $locale,
        private readonly string $defaultLocale,
        private readonly array $locales,
        private readonly array $paths,
        private readonly string $source = 'es'
    ) {
        if (! in_array($defaultLocale, $locales, true)) {
            throw new InvalidArgumentException("APP_LOCALE [{$defaultLocale}] no está en APP_LOCALES (" . implode(', ', $locales) . ').');
        }

        $this->setLocale($locale);
    }

    /**
     * Traduce el texto al idioma activo (o al indicado) y reemplaza :marcadores.
     *
     * @param array<string, string|int|float|\Stringable> $replace
     */
    public function get(string $text, array $replace = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;
        $line = $this->load($locale)[$text] ?? $text;

        return self::replace($line, $replace);
    }

    /**
     * Elige la forma según la cantidad: 'una tarea|:count tareas' o '{0} ninguna|una|:count'
     * (con tres formas, la primera es para 0). :count se reemplaza solo.
     *
     * @param array<string, string|int|float|\Stringable> $replace
     */
    public function choice(string $text, int|float $count, array $replace = [], ?string $locale = null): string
    {
        $forms = explode('|', $this->get($text, [], $locale));
        $forms = array_map(static fn (string $form): string => (string) preg_replace('/^\{0\}\s*/', '', $form), $forms);

        $form = match (true) {
            count($forms) >= 3 && $count == 0 => $forms[0],
            count($forms) >= 3 => abs($count) == 1 ? $forms[1] : $forms[2],
            count($forms) === 2 => abs($count) == 1 ? $forms[0] : $forms[1],
            default => $forms[0],
        };

        return self::replace($form, ['count' => $count] + $replace);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Cambia el idioma activo.
     *
     * @throws InvalidArgumentException Si no está en APP_LOCALES
     */
    public function setLocale(string $locale): void
    {
        if (! in_array($locale, $this->locales, true)) {
            throw new InvalidArgumentException("El idioma [{$locale}] no está en APP_LOCALES (" . implode(', ', $this->locales) . ').');
        }

        $this->locale = $locale;
    }

    public function defaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return $this->locales;
    }

    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->locales, true);
    }

    /**
     * Idioma de los textos del código (las claves).
     */
    public function sourceLocale(): string
    {
        return $this->source;
    }

    /**
     * Ruta que atiende la petición actual, para generar su URL en otro idioma.
     *
     * @internal La registra el middleware SetLocale.
     * @param array<string, mixed> $parameters
     */
    public function setCurrentRoute(?Route $route, array $parameters = [], string $path = '/'): void
    {
        $this->route = $route;
        $this->parameters = $parameters;
        $this->path = $path;
    }

    /**
     * Path de la petición actual.
     */
    public function currentPath(): string
    {
        return $this->path;
    }

    public function currentRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function currentParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Todas las traducciones de un idioma (útil para pasarlas a JavaScript).
     *
     * @return array<string, string>
     */
    public function all(?string $locale = null): array
    {
        return $this->load($locale ?? $this->locale);
    }

    /**
     * @return array<string, string>
     */
    private function load(string $locale): array
    {
        if (isset($this->lines[$locale])) {
            return $this->lines[$locale];
        }

        $lines = [];

        foreach ($this->paths as $path) {
            $files = [$path . '/' . $locale . '.php', ...(glob($path . '/' . $locale . '/*.php') ?: [])];

            foreach ($files as $file) {
                if (! is_file($file)) {
                    continue;
                }

                $data = require $file;

                if (! is_array($data)) {
                    throw new InvalidArgumentException("El archivo de idioma {$file} debe retornar un array ['texto en español' => 'traducción'].");
                }

                foreach ($data as $key => $value) {
                    if (is_string($key) && is_string($value)) {
                        $lines[$key] = $value;
                    }
                }
            }
        }

        return $this->lines[$locale] = $lines;
    }

    /**
     * @param array<string, string|int|float|\Stringable> $replace
     */
    private static function replace(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        // Los marcadores más largos primero: :nombre_completo no debe partirse por :nombre
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pairs = [];

        foreach ($replace as $key => $value) {
            $pairs[':' . $key] = (string) $value;
        }

        return strtr($line, $pairs);
    }
}
