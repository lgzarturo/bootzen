<?php

/**
 * HtmlString.php
 *
 * HTML ya seguro que {{ }} imprime sin volver a escapar (por ejemplo, el contenido de un slot).
 *
 * @package    BootZen\View
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\View;

use Stringable;

/**
 * Fragmento de HTML confiable.
 */
final class HtmlString implements Stringable
{
    public function __construct(private readonly string $html = '')
    {
    }

    /**
     * Indica si el contenido está vacío (ignorando espacios).
     */
    public function isEmpty(): bool
    {
        return trim($this->html) === '';
    }

    /**
     * Indica si hay contenido.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
