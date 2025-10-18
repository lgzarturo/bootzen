<?php

/**
 * Controlador principal de rutas públicas y API para BootZen
 *
 * Este archivo define el HomeController, responsable de registrar las rutas principales,
 * páginas públicas y endpoints de API en el framework BootZen. Gestiona la renderización
 * de vistas, el uso de componentes reutilizables y la integración de cache para mejorar
 * el rendimiento. Implementa el patrón Controller y sigue las mejores prácticas de diseño
 * para aplicaciones web modernas.
 *
 * @package    BootZen\Controllers
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.8
 * @since      1.0.0
 *
 * @see        BootZen\Core\Router
 * @see        BootZen\Components\Layout
 */

declare(strict_types=1);

namespace BootZen\Controllers;

use BootZen\Components\Alert;
use BootZen\Components\Button;
use BootZen\Components\Card;
use BootZen\Components\Layout;
use BootZen\Components\Navigation;
use BootZen\Core\Cache;
use BootZen\Core\Component;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;

/**
 * Clase HomeController
 *
 * Registra las rutas públicas y endpoints de API para la aplicación BootZen.
 * Gestiona la renderización de páginas, el uso de componentes y la integración de cache.
 *
 * - Define rutas para la página principal, contacto, API de proyectos y componentes dinámicos.
 * - Utiliza componentes desacoplados para Layout, Navegación, Card, Button y Alert.
 * - Aplica cache global a componentes para optimizar el rendimiento.
 *
 */
class HomeController
{
    /**
     * Registra todas las rutas públicas y API en el router de BootZen.
     *
     * Este método configura las rutas principales, páginas públicas, endpoints de API y componentes
     * dinámicos, integrando cache y renderización de vistas. Es el punto de entrada para la definición
     * de rutas en la aplicación.
     *
     * @param Router $router Instancia del router de BootZen
     * @return void
     *
     * @example
     *   $router = new Router();
     *   HomeController::register($router);
     */
    public static function register(Router $router): void
    {
        // Configurar cache global para componentes
        $cache = new Cache();
        Component::setCache($cache);

        // Página principal
        $router->get('/', function (Request $request) {
            /**
             * Renderiza la página principal con hero y proyectos destacados.
             * Utiliza Layout, Navigation, Card y Button como componentes.
             *
             * @param Request $request Solicitud HTTP entrante
             * @return Response Respuesta HTML renderizada
             */
            $layout = new Layout(
                [
                    'title' => 'Mi Portafolio - BootZen',
                    'description' => 'Portafolio profesional creado con BootZen Framework',
                ],
                [
                    'header' => self::getHeader(),
                    'default' => function () {
                        $hero = '<div class="text-center py-20">';
                        $hero .= '<h1 class="text-5xl font-bold text-gray-900 mb-6">Hola, soy Arturo López</h1>';
                        $hero .= '<p class="text-xl text-gray-600 mb-8">'
                            . 'Desarrollador Full Stack especializado en Java y PHP'
                            . '</p>';
                        $hero .= (new Button([
                            'variant' => 'primary',
                            'size' => 'lg',
                        ], [
                            'default' => 'Ver mis proyectos',
                        ]))->render();
                        $hero .= '</div>';

                        $projects = '<div class="py-16">';
                        $projects .= '<h2 class="text-3xl font-bold text-center mb-12">Proyectos Destacados</h2>';
                        $projects .= '<div class="grid md:grid-cols-3 gap-8">';

                        $projectsData = [
                            [
                                'title' => 'E-commerce Platform',
                                'subtitle' => 'PHP, MySQL, Redis',
                                'image' => 'https://via.placeholder.com/400x200',
                                'description' => 'Plataforma de comercio electrónico completa con sistema de pagos y '
                                    . 'gestión de inventario.',
                            ],
                            [
                                'title' => 'Task Management App',
                                'subtitle' => 'React, Node.js, MongoDB',
                                'image' => 'https://via.placeholder.com/400x200',
                                'description' => 'Aplicación de gestión de tareas con colaboración en tiempo real.',
                            ],
                            [
                                'title' => 'Analytics Dashboard',
                                'subtitle' => 'Vue.js, Laravel, PostgreSQL',
                                'image' => 'https://via.placeholder.com/400x200',
                                'description' => 'Dashboard de analíticas con visualizaciones interactivas y '
                                    . 'reportes automáticos.',
                            ],
                        ];

                        foreach ($projectsData as $project) {
                            $projects .= (new Card([
                                'title' => $project['title'],
                                'subtitle' => $project['subtitle'],
                                'image' => $project['image'],
                            ], [
                                'default' => $project['description'],
                                'footer' => (new Button([
                                    'variant' => 'ghost',
                                    'size' => 'sm',
                                ], [
                                    'default' => 'Ver proyecto',
                                ]))->render(),
                            ]))->cache("project_card_" . md5($project['title']), 3600)->render();
                        }

                        $projects .= '</div></div>';

                        return $hero . $projects;
                    },
                    'footer' => self::getFooter(),
                ]
            );

            return Response::html($layout->render());
        });

        // Página de contacto con formulario
        $router->get('/contact', function (Request $request) {
            /**
             * Renderiza la página de contacto con formulario y alertas.
             *
             * @param Request $request Solicitud HTTP entrante
             * @return Response Respuesta HTML renderizada
             */
            $layout = new Layout(
                [
                    'title' => 'Contacto - Mi Portafolio',
                ],
                [
                    'header' => self::getHeader('/contact'),
                    'default' => function () {
                        $content = '<div class="max-w-2xl mx-auto">';
                        $content .= '<h1 class="text-4xl font-bold text-center mb-8">Contacto</h1>';

                        if (isset($_GET['success'])) {
                            $content .= (new Alert([
                                'type' => 'success',
                                'title' => '¡Mensaje enviado!',
                                'dismissible' => true,
                            ], [
                                'default' => 'Tu mensaje ha sido enviado correctamente. Te responderé pronto.',
                            ]))->render();
                        }

                        $content .= '<div class="bg-white p-8 rounded-lg shadow-md">';
                        $content .= '<form action="/contact" method="POST" class="space-y-6">';

                        $content .= '<div>';
                        $content .= '<label class="block text-sm font-medium text-gray-700 mb-2">Nombre *</label>';
                        $content .= '<input type="text" name="name" required '
                            . 'class="w-full px-3 py-2 border border-gray-300 rounded-lg '
                            . 'focus:outline-none focus:ring-2 focus:ring-blue-500">';
                        $content .= '</div>';

                        $content .= '<div>';
                        $content .= '<label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>';
                        $content .= '<input type="email" name="email" required '
                            . 'class="w-full px-3 py-2 border border-gray-300 rounded-lg '
                            . 'focus:outline-none focus:ring-2 focus:ring-blue-500">';
                        $content .= '</div>';

                        $content .= '<div>';
                        $content .= '<label class="block text-sm font-medium text-gray-700 mb-2">Mensaje *</label>';
                        $content .= '<textarea name="message" rows="5" required '
                            . 'class="w-full px-3 py-2 border border-gray-300 rounded-lg '
                            . 'focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>';
                        $content .= '</div>';

                        $content .= (new Button([
                            'type' => 'submit',
                            'variant' => 'primary',
                            'size' => 'lg',
                        ], [
                            'default' => 'Enviar mensaje',
                        ]))->render();

                        $content .= '</form>';
                        $content .= '</div>';
                        $content .= '</div>';

                        return $content;
                    },
                    'footer' => self::getFooter(),
                ]
            );

            return Response::html($layout->render());
        });

        // Procesar formulario de contacto
        $router->post('/contact', function (Request $request) {
            /**
             * Procesa el envío del formulario de contacto.
             *
             * @param Request $request Solicitud HTTP POST
             * @return Response Redirección tras procesar el formulario
             *
             * @todo Implementar lógica de envío de email y validación avanzada
             */
            $name = $request->getBody('name');
            $email = $request->getBody('email');
            $message = $request->getBody('message');

            // Aquí procesarías el formulario (enviar email, guardar en DB, etc.)
            // Por ahora solo redirigimos con éxito
            return Response::redirect('/contact?success=1');
        });

        // API endpoint para obtener proyectos
        $router->get('/api/projects', function (Request $request) {
            /**
             * Devuelve la lista de proyectos destacados en formato JSON.
             *
             * @param Request $request Solicitud HTTP entrante
             * @return Response Respuesta JSON con proyectos
             */
            $projects = [
                [
                    'id' => 1,
                    'title' => 'E-commerce Platform',
                    'description' => 'Plataforma de comercio electrónico completa',
                    'technologies' => ['PHP', 'MySQL', 'Redis'],
                    'status' => 'completed',
                ],
                [
                    'id' => 2,
                    'title' => 'Task Management App',
                    'description' => 'Aplicación de gestión de tareas',
                    'technologies' => ['React', 'Node.js', 'MongoDB'],
                    'status' => 'in_progress',
                ],
            ];

            return Response::json([
                'success' => true,
                'data' => $projects,
                'total' => count($projects),
            ]);
        });

        // Componente dinámico con cache
        $router->get('/dynamic-component', function (Request $request) {
            /**
             * Renderiza un componente Card dinámico con cache de 1 hora.
             *
             * @param Request $request Solicitud HTTP entrante
             * @return Response Respuesta HTML con componente cacheado
             *
             * @example
             *   // Acceso: /dynamic-component
             */
            $dynamicCard = (new Card([
                'title' => 'Componente Dinámico',
                'subtitle' => 'Generado en ' . date('Y-m-d H:i:s'),
            ], [
                'default' => 'Este componente se cachea por 1 hora. '
                    . 'Recarga la página para ver que el timestamp no cambia.',
                'footer' => (new Button([
                    'variant' => 'secondary',
                ], [
                    'default' => 'Botón cacheado',
                ]))->render(),
            ]))->cache('dynamic_card_' . date('Y-m-d-H'), 3600);

            $layout = new Layout(
                [
                    'title' => 'Componente Dinámico',
                ],
                [
                    'default' => '<div class="max-w-2xl mx-auto">' . $dynamicCard->render() . '</div>',
                ]
            );

            return Response::html($layout->render());
        });
    }

    /**
     * Renderiza el header de la aplicación.
     *
     * @return string HTML del header
     */
    private static function getHeader(string $currentPath = '/'): string
    {
        return (new Navigation([
            'brand' => 'Mi Portafolio',
            'items' => [
                ['href' => '/', 'label' => 'Inicio'],
                ['href' => '/about', 'label' => 'Acerca de'],
                ['href' => '/projects', 'label' => 'Proyectos'],
                ['href' => '/contact', 'label' => 'Contacto'],
            ],
            'currentPath' => $currentPath,
        ]))->render();
    }

    /**
     * Renderiza el footer de la aplicación.
     *
     * @return string HTML del footer
     */
    private static function getFooter(): string
    {
        return '<footer class="bg-gray-900 text-white py-8 mt-16">
                    <div class="container mx-auto px-4 text-center">
                        <p>&copy; 2024 Arturo López. Todos los derechos reservados.</p>
                    </div>
                </footer>';
    }
}
