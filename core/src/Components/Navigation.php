<?php

/**
 * Componente Navigation para BootZen
 *
 * Este archivo define el componente Navigation, responsable de renderizar la barra de navegación
 * principal de la aplicación en el framework BootZen. Permite mostrar la marca, enlaces de navegación
 * y resalta el enlace activo, siguiendo el patrón de diseño Componente y las mejores prácticas de
 * accesibilidad y usabilidad.
 *
 * @package    BootZen\Components
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.4
 * @since      1.0.0
 *
 * @see        BootZen\Core\Component
 *
 */

declare(strict_types=1);

namespace BootZen\Components;

use BootZen\Core\Component;

/**
 * Clase Navigation
 *
 * Renderiza la barra de navegación principal con soporte para marca, enlaces y estado activo.
 * Utiliza utilidades de composición de clases y atributos para garantizar flexibilidad y seguridad.
 *
 * - Permite personalizar la marca y los enlaces.
 * - Resalta el enlace activo según la ruta actual.
 * - Optimizado para accesibilidad y responsive.
 *
 */
class Navigation extends Component
{
    /**
     * Renderiza la plantilla HTML de la barra de navegación según las propiedades configuradas.
     *
     * @return string HTML de la barra de navegación
     *
     * @example
     *   echo (new Navigation([
     *     'brand' => 'MiApp',
     *     'items' => [
     *       ['href' => '/', 'label' => 'Inicio'],
     *       ['href' => '/about', 'label' => 'Acerca de']
     *     ],
     *     'currentPath' => '/about'
     *   ]))->render();
     */
    protected function template(): string
    {
        $brand = $this->prop('brand', 'BootZen');
        $items = $this->prop('items', []);
        $currentPath = $this->prop('currentPath', '/');

        $html = '<nav class="bg-white shadow-sm border-b">';
        $html .= '<div class="container mx-auto px-4">';
        $html .= '<div class="flex justify-between items-center h-16">';
        $html .= "<div class=\"font-bold text-xl text-gray-900\">{$this->e($brand)}</div>";
        if (! empty($items)) {
            $html .= '<div class="hidden md:flex space-x-8">';
            foreach ($items as $item) {
                $isActive = $currentPath === $item['href'];
                $classes = $this->classNames([
                    'px-3 py-2 text-sm font-medium transition-colors',
                    'text-blue-600 border-b-2 border-blue-600' => $isActive,
                    'text-gray-700 hover:text-blue-600' => ! $isActive,
                ]);
                $html .= "<a href=\"{$this->e($item['href'])}\" class=\"{$classes}\">{$this->e($item['label'])}</a>";
            }
            $html .= '</div>';
        }
        $html .= '<div class="md:hidden">';
        $html .= '<button class="text-gray-700 hover:text-blue-600">';
        $html .= '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
        $html .= '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>';
        $html .= '</svg>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div></div></nav>';

        return $html;
    }
}
