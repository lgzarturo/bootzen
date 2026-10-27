<?php

/**
 * Component.php
 *
 * Base para componentes con lógica. Un componente siempre es una vista en
 * resources/views/components/; la clase es opcional y solo prepara datos para esa vista.
 *
 * - Los parámetros del constructor se llenan con los atributos del mismo nombre.
 * - Las propiedades públicas y lo que retorne data() quedan disponibles en la vista.
 *
 * @package    BootZen\View
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\View;

/**
 * Componente de vista con lógica.
 *
 * @example
 *   // app/View/Components/Alert.php, para <x-alert tipo="error">
 *   final class Alert extends Component
 *   {
 *       public function __construct(public string $tipo = 'info') {}
 *
 *       public function data(): array
 *       {
 *           return ['icono' => $this->tipo === 'error' ? '⚠' : 'ℹ'];
 *       }
 *   }
 */
abstract class Component
{
    /**
     * Datos adicionales para la vista del componente.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [];
    }
}
