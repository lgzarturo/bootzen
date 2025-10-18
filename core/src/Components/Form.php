<?php

/**
 * Componente Formulario (Form)
 *
 * Este archivo define el componente Form, responsable de renderizar formularios HTML reutilizables en el
 * framework BootZen. Permite la integración de protección CSRF, personalización de método y acción, y la
 * inclusión dinámica de contenido mediante slots.
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
 */

declare(strict_types=1);

namespace BootZen\Components;

use BootZen\Core\Component;

/**
 * Clase Form
 *
 * Renderiza un formulario HTML seguro y flexible, integrando protección CSRF y permitiendo la personalización de atributos clave.
 * Utiliza el patrón de Componente y se integra con el sistema de slots de BootZen para contenido dinámico.
 *
 * Consideraciones de seguridad: Incluye token CSRF para mitigar ataques de falsificación de solicitudes.
 * Consideraciones de rendimiento: Generación eficiente de HTML, sin lógica pesada en el renderizado.
 */
class Form extends Component
{
    /**
     * Renderiza la plantilla HTML del formulario según las propiedades y slots configurados.
     *
     * Propiedades:
     * - action: URL a la que se enviará el formulario (string, por defecto: '').
     * - method: Método HTTP para el envío (string, por defecto: 'POST').
     * - csrfToken: Token CSRF para protección (string, por defecto: '').
     *
     * Slots:
     * - default: Contenido del formulario (inputs, botones, etc.).
     *
     * @return string HTML del formulario renderizado
     *
     * @example
     *   echo (new Form(['action' => '/submit', 'csrfToken' => $token], [
     *       'default' => '<input type="text" name="name"><button type="submit">Enviar</button>'
     *   ]))->render();
     */
    protected function template(): string
    {
        $action = $this->prop('action', '');
        $method = $this->prop('method', 'POST');
        $csrfToken = $this->prop('csrfToken', '');

        $html = "<form action=\"{$this->e($action)}\" method=\"{$this->e($method)}\" class=\"space-y-6\">";

        // CSRF Token
        if ($csrfToken) {
            $html .= "<input type=\"hidden\" name=\"_token\" value=\"{$this->e($csrfToken)}\">";
        }

        $html .= $this->slot();
        $html .= '</form>';

        return $html;
    }
}
