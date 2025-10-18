<?php

/**
 * Componente Alert de BootZen Framework.
 *
 * Este archivo define el componente visual Alert, responsable de mostrar mensajes contextuales
 * (informativos, éxito, advertencia, error) con soporte para título, icono y cierre opcional.
 * Utiliza utilidades de Tailwind CSS y sigue el patrón de Componente de Presentación.
 *
 * @package    BootZen\Components
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.5
 * @since      1.0.0
 *
 * @see        BootZen\Core\Component
 *
 * Patrones de diseño:
 * - Presentational Component
 * - Template Method (para renderizado)
 *
 * Consideraciones de seguridad:
 * - Escapa el título y el contenido para evitar XSS
 *
 * Consideraciones de rendimiento:
 * - Renderizado eficiente y reutilizable
 *
 * Ejemplo de uso:
 * <code>
 * &lt;x-alert type="success" title="Operación exitosa" dismissible&gt;
 *     ¡Los datos se guardaron correctamente!
 * &lt;/x-alert&gt;
 * </code>
 */

declare(strict_types=1);

namespace BootZen\Components;

use BootZen\Core\Component;

/**
 * Clase Alert
 *
 * Componente visual reutilizable para mostrar alertas contextuales en la interfaz.
 * Permite personalizar tipo, título, contenido y cierre.
 */
class Alert extends Component
{
    /**
     * Genera el HTML de la alerta según las propiedades recibidas.
     *
     * @return string HTML renderizado del componente Alert
     *
     * @throws \Exception Si ocurre un error en la obtención de propiedades
     *
     * @example
     * // Ejemplo de uso en Blade:
     * // <x-alert type="error" title="Error" dismissible>
     * //     Ha ocurrido un error inesperado.
     * // </x-alert>
     */
    protected function template(): string
    {
        $type = $this->prop('type', 'info');
        $title = $this->prop('title');
        $dismissible = $this->prop('dismissible', false);
        $classes = $this->classNames([
            'p-4 rounded-lg border',
            'bg-blue-50 border-blue-200 text-blue-800' => $type === 'info',
            'bg-green-50 border-green-200 text-green-800' => $type === 'success',
            'bg-yellow-50 border-yellow-200 text-yellow-800' => $type === 'warning',
            'bg-red-50 border-red-200 text-red-800' => $type === 'error',
        ]);
        $iconClasses = $this->classNames([
            'w-5 h-5 mr-3 flex-shrink-0',
            'text-blue-600' => $type === 'info',
            'text-green-600' => $type === 'success',
            'text-yellow-600' => $type === 'warning',
            'text-red-600' => $type === 'error',
        ]);
        $html = "<div class=\"{$classes}\">";
        $html .= '<div class="flex">';
        $html .= "<div class=\"{$iconClasses}\">";
        $html .= $this->getIcon($type);
        $html .= '</div>';
        $html .= '<div class="flex-1">';
        if ($title) {
            $html .= "<h4 class=\"font-medium mb-1\">{$this->e($title)}</h4>";
        }
        $html .= "<div>{$this->slot()}</div>";
        $html .= '</div>';
        if ($dismissible) {
            $html .= '<div class="ml-4">';
            $html .= '<button class="text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.parentElement.remove()">';
            $html .= '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">';
            $html .= '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>';
            $html .= '</svg>';
            $html .= '</button>';
            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Obtiene el SVG correspondiente al tipo de alerta.
     *
     * @internal Método de uso interno del framework BootZen.
     *
     * @param string $type Tipo de alerta ('info', 'success', 'warning', 'error')
     * @return string SVG del icono correspondiente
     *
     * @example
     * $this->getIcon('success');
     */
    private function getIcon(string $type): string
    {
        $icons = [
            'info' => '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>',
            'success' => '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
            'warning' => '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
            'error' => '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>',
        ];

        return $icons[$type] ?? $icons['info'];
    }
}
