<?php

/**
 * Componente Button reutilizable para BootZen
 *
 * Este archivo define el componente Button, responsable de renderizar botones personalizables
 * y accesibles en el framework BootZen. Permite configurar variantes, tamaños, estados y eventos,
 * siguiendo el patrón de diseño Componente y las mejores prácticas de accesibilidad y seguridad.
 *
 * @package    BootZen\Components
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.5
 * @since      1.0.0
 *
 * @see        BootZen\Core\Component
 */

declare(strict_types=1);

namespace BootZen\Components;

use BootZen\Core\Component;

/**
 * Clase Button
 *
 * Renderiza un botón HTML personalizable con soporte para variantes, tamaños, deshabilitado y eventos.
 * Utiliza utilidades de composición de clases y atributos para garantizar flexibilidad y seguridad.
 *
 * - Permite variantes: primary, secondary, danger, ghost.
 * - Soporta tamaños: sm, md, lg.
 * - Gestiona atributos de accesibilidad y eventos JS.
 */
class Button extends Component
{
    /**
     * Renderiza la plantilla HTML del botón según las propiedades configuradas.
     *
     * @return string HTML del botón renderizado
     *
     * @example
     *   echo (new Button(['variant' => 'danger', 'size' => 'lg'], ['default' => 'Eliminar']))->render();
     */
    protected function template(): string
    {
        $variant = $this->prop('variant', 'primary');
        $size = $this->prop('size', 'md');
        $disabled = $this->prop('disabled', false);
        $type = $this->prop('type', 'button');
        $onClick = $this->prop('onClick', '');
        $classes = $this->classNames([
            'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2',
            'px-4 py-2 text-sm' => $size === 'sm',
            'px-6 py-3 text-base' => $size === 'md',
            'px-8 py-4 text-lg' => $size === 'lg',
            'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500' => $variant === 'primary',
            'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500' => $variant === 'secondary',
            'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500' => $variant === 'danger',
            'bg-transparent text-blue-600 hover:bg-blue-50 focus:ring-blue-500' => $variant === 'ghost',
            'opacity-50 cursor-not-allowed' => $disabled,
        ]);
        $attributes = [
            'type' => $type,
            'class' => $classes,
        ];
        if ($disabled) {
            $attributes['disabled'] = 'disabled';
        }
        if ($onClick) {
            $attributes['onclick'] = $onClick;
        }
        $attributeString = $this->buildAttributes($attributes);

        return "<button {$attributeString}>{$this->slot()}</button>";
    }

    /**
     * Construye la cadena de atributos HTML a partir de un array asociativo.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param array<string, string|bool> $attributes Atributos HTML clave-valor
     * @return string Cadena de atributos HTML escapados
     */
    private function buildAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = $key . '="' . $this->e($value) . '"';
        }

        return implode(' ', $parts);
    }
}
