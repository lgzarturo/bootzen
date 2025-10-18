<?php

/**
 * Componente Input (campo de formulario)
 *
 * Este archivo define el componente Input, encargado de renderizar campos de entrada HTML reutilizables y accesibles
 * en el framework BootZen. Permite la personalización de tipo, nombre, valor, placeholder, etiqueta, validación y
 * mensajes de error, integrándose con el sistema de slots y propiedades del framework.
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
 * Clase Input
 *
 * Renderiza un campo de entrada HTML flexible y seguro, soportando validación, etiquetas, mensajes de error y estilos
 * dinámicos. Utiliza el patrón de Componente y se integra con el sistema de propiedades de BootZen.
 *
 * Consideraciones de seguridad: Escapa todos los valores para prevenir XSS.
 * Consideraciones de rendimiento: Renderizado eficiente y sin lógica pesada.
 *
 * Dependencias: Extiende {@see Component} del núcleo de BootZen.
 *
 * Ejemplo de uso:
 * <code>
 * <?= (new Input(['name' => 'email', 'type' => 'email', 'label' => 'Correo']))->render() ?>
 * </code>
 */
class Input extends Component
{
    /**
     * Renderiza el campo de entrada HTML completo, incluyendo etiqueta, input y mensaje de error.
     *
     * Obtiene las propiedades 'type', 'name', 'value', 'placeholder', 'label', 'error' y 'required' para construir
     * dinámicamente el input. Aplica clases condicionales y escapa todos los valores para seguridad.
     *
     * @internal Método protegido del framework BootZen. No debe invocarse directamente fuera del ciclo de renderizado.
     *
     * @return string HTML seguro del campo de entrada
     *
     * @example
     * // Renderizar un input de contraseña requerido
     * $input = new Input([
     *     'name' => 'password',
     *     'type' => 'password',
     *     'label' => 'Contraseña',
     *     'required' => true
     * ]);
     * echo $input->render();
     *
     * @todo Permitir atributos personalizados adicionales (autocomplete, maxlength, etc.)
     */
    protected function template(): string
    {
        /** @var string $type Tipo de input (text, email, password, etc.) */
        $type = $this->prop('type', 'text');
        /** @var string $name Nombre del campo */
        $name = $this->prop('name', '');
        /** @var string $value Valor por defecto */
        $value = $this->prop('value', '');
        /** @var string $placeholder Texto de ayuda */
        $placeholder = $this->prop('placeholder', '');
        /** @var string $label Etiqueta visible */
        $label = $this->prop('label', '');
        /** @var string $error Mensaje de error */
        $error = $this->prop('error', '');
        /** @var bool $required Indica si es obligatorio */
        $required = $this->prop('required', false);

        $inputClasses = $this->classNames([
            'w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500',
            'border-gray-300' => ! $error,
            'border-red-300 focus:ring-red-500' => $error,
        ]);

        $html = '<div>';

        // Label
        if ($label) {
            $html .= "<label class=\"block text-sm font-medium text-gray-700 mb-2\">";
            $html .= $this->e($label);
            if ($required) {
                $html .= '<span class="text-red-500 ml-1">*</span>';
            }
            $html .= '</label>';
        }

        // Input
        $attributes = [
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'placeholder' => $placeholder,
            'class' => $inputClasses,
        ];

        if ($required) {
            $attributes['required'] = 'required';
        }

        $attributeString = $this->buildAttributes($attributes);
        $html .= "<input {$attributeString}>";

        // Mensaje de error
        if ($error) {
            $html .= "<p class=\"mt-1 text-sm text-red-600\">{$this->e($error)}</p>";
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Construye la cadena de atributos HTML a partir de un array asociativo.
     *
     * @internal Solo para uso interno del framework BootZen.
     *
     * @param array<string, string|bool> $attributes Atributos HTML clave-valor
     * @return string Cadena de atributos HTML escapados
     *
     * @example
     * // Generar atributos para un input
     * $this->buildAttributes([
     *     'type' => 'email',
     *     'name' => 'correo',
     *     'required' => true
     * ]);
     */
    private function buildAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value !== '') {
                $parts[] = $key . '="' . $this->e($value) . '"';
            }
        }

        return implode(' ', $parts);
    }
}
