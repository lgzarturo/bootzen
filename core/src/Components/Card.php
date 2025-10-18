<?php

/**
 * Componente Card reutilizable para BootZen
 *
 * Este archivo define el componente Card, responsable de renderizar tarjetas visuales
 * y flexibles en el framework BootZen. Permite mostrar imágenes, títulos, subtítulos,
 * contenido y slots personalizados, siguiendo el patrón de diseño Componente y las mejores
 * prácticas de accesibilidad y rendimiento.
 *
 * @package    BootZen\Components
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.8
 * @since      1.0.0
 *
 * @see        BootZen\Core\Component
 *
 */

declare(strict_types=1);

namespace BootZen\Components;

use BootZen\Core\Component;

/**
 * Clase Card
 *
 * Renderiza una tarjeta HTML flexible con soporte para imagen, título, subtítulo, contenido y slots.
 * Utiliza utilidades de composición de clases y atributos para garantizar flexibilidad y seguridad.
 *
 * - Permite slots: header, default, footer.
 * - Soporta personalización de padding, sombra y clases adicionales.
 * - Gestiona atributos de accesibilidad y sanitización de datos.
 */

class Card extends Component
{
    /**
     * Renderiza la plantilla HTML de la tarjeta según las propiedades y slots configurados.
     *
     * @return string HTML de la tarjeta renderizada
     *
     * @example
     *   echo (new Card([
     *     'title' => 'Título',
     *     'subtitle' => 'Subtítulo',
     *     'image' => 'img.jpg'
     *   ], [
     *     'default' => 'Contenido principal',
     *     'footer' => 'Pie de tarjeta'
     *   ]))->render();
     */
    protected function template(): string
    {
        $title = $this->prop('title');
        $subtitle = $this->prop('subtitle');
        $image = $this->prop('image');
        $padding = $this->prop('padding', 'p-6');
        $shadow = $this->prop('shadow', 'shadow-md');
        $classes = $this->classNames([
            'bg-white rounded-lg overflow-hidden',
            $shadow,
            $this->prop('class', ''),
        ]);
        $html = "<div class=\"{$classes}\">";
        if ($image) {
            $html .= "<img src=\"{$this->e($image)}\" alt=\"{$this->e($title)}\" class=\"w-full h-48 object-cover\">";
        }
        $html .= "<div class=\"{$padding}\">";
        if ($this->hasSlot('header')) {
            $html .= "<div class=\"mb-4\">{$this->slot('header')}</div>";
        } elseif ($title || $subtitle) {
            $html .= "<div class=\"mb-4\">";
            if ($title) {
                $html .= "<h3 class=\"text-xl font-semibold text-gray-900\">{$this->e($title)}</h3>";
            }
            if ($subtitle) {
                $html .= "<p class=\"text-gray-600 mt-1\">{$this->e($subtitle)}</p>";
            }
            $html .= "</div>";
        }
        $html .= "<div class=\"text-gray-700\">{$this->slot()}</div>";
        if ($this->hasSlot('footer')) {
            $html .= "<div class=\"mt-4 pt-4 border-t border-gray-200\">{$this->slot('footer')}</div>";
        }
        $html .= "</div></div>";

        return $html;
    }
}
