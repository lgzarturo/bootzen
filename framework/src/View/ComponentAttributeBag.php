<?php

/**
 * ComponentAttributeBag.php
 *
 * Atributos HTML que recibe un componente y que no son props. En la vista del componente
 * se imprimen con {{ $attributes }} y se combinan con merge() o class().
 *
 * @package    BootZen\View
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\View;

use ArrayIterator;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * Bolsa de atributos de un componente.
 *
 * @implements IteratorAggregate<string, mixed>
 * @example <div {{ $attributes->merge(['class' => 'rounded p-4']) }}>
 * @example <div {{ $attributes->class(['p-4', 'bg-red-50' => $error]) }}>
 */
final class ComponentAttributeBag implements IteratorAggregate, Stringable
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(private readonly array $attributes = [])
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * @param list<string> $keys
     */
    public function only(array $keys): self
    {
        return new self(array_intersect_key($this->attributes, array_flip($keys)));
    }

    /**
     * @param list<string> $keys
     */
    public function except(array $keys): self
    {
        return new self(array_diff_key($this->attributes, array_flip($keys)));
    }

    /**
     * Combina con atributos por defecto. Las clases y los estilos se suman; el resto
     * de atributos recibidos reemplaza a los valores por defecto.
     *
     * @param array<string, mixed> $defaults
     */
    public function merge(array $defaults): self
    {
        $merged = array_merge($defaults, $this->attributes);

        foreach (['class' => ' ', 'style' => '; '] as $key => $glue) {
            if (isset($defaults[$key], $this->attributes[$key]) && $this->attributes[$key] !== true) {
                $merged[$key] = trim(self::text($defaults[$key]) . $glue . self::text($this->attributes[$key]));
            }
        }

        return new self($merged);
    }

    /**
     * Agrega clases, admitiendo clases condicionales: ['p-4', 'bg-red-50' => $error].
     *
     * @param string|array<int|string, mixed> $classes
     */
    public function class(string|array $classes): self
    {
        return $this->merge(['class' => self::classList($classes)]);
    }

    /**
     * Arma una lista de clases a partir de un array con condiciones.
     *
     * @param string|array<int|string, mixed> $classes
     */
    public static function classList(string|array $classes): string
    {
        if (is_string($classes)) {
            return $classes;
        }

        $list = [];
        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                $list[] = self::text($value);
            } elseif ($value) {
                $list[] = $key;
            }
        }

        return implode(' ', array_filter($list, static fn (string $class): bool => $class !== ''));
    }

    /**
     * @return Traversable<string, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }

    /**
     * Imprime los atributos como HTML escapado. true imprime solo el nombre; false y null se omiten.
     */
    public function __toString(): string
    {
        $html = [];

        foreach ($this->attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $html[] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

                continue;
            }

            $text = is_array($value) ? (string) json_encode($value, JSON_UNESCAPED_UNICODE) : self::text($value);
            $html[] = sprintf(
                '%s="%s"',
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            );
        }

        return implode(' ', $html);
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
    }
}
