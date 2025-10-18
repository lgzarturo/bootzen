<?php

/**
 * Componente Layout base para BootZen
 *
 * Este archivo define el componente Layout, responsable de estructurar la plantilla HTML principal
 * de la aplicación en el framework BootZen. Permite definir el título, descripción, idioma y slots
 * para cabecera, pie, scripts y contenido dinámico, siguiendo el patrón de diseño Componente y las
 * mejores prácticas de accesibilidad y SEO.
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
 * Clase Layout
 *
 * Renderiza la estructura HTML base de la aplicación, permitiendo la inserción de contenido
 * dinámico y slots personalizados para head, header, footer y scripts.
 *
 * - Permite personalizar título, descripción y lenguaje.
 * - Soporta slots: head, header, default, footer, scripts.
 * - Optimizado para SEO y accesibilidad.
 */
class Layout extends Component
{
    /**
     * Renderiza la plantilla HTML principal con los slots y propiedades configuradas.
     *
     * @return string HTML completo de la página
     *
     * @example
     *   echo (new Layout([
     *     'title' => 'Mi App',
     *     'description' => 'Descripción SEO',
     *     'lang' => 'es'
     *   ], [
     *     'header' => '<header>...</header>',
     *     'default' => '<section>Contenido</section>',
     *     'footer' => '<footer>...</footer>'
     *   ]))->render();
     */
    protected function template(): string
    {
        $title = $this->prop('title', 'BootZen App');
        $description = $this->prop('description', '');
        $lang = $this->prop('lang', 'es');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$this->e($lang)}">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$this->e($title)}</title>
            {$this->when(! empty($description), "<meta name=\"description\" content=\"{$this->e($description)}\">")}
            <script src="https://cdn.tailwindcss.com"></script>
            {$this->slot('head')}
        </head>
        <body class="bg-gray-50 min-h-screen">
            {$this->slot('header')}
            <main class="container mx-auto px-4 py-8">
                {$this->slot()}
            </main>
            {$this->slot('footer')}
            {$this->slot('scripts')}
        </body>
        </html>
        HTML;
    }
}
