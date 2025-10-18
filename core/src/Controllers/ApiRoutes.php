<?php

/**
 * Definición de rutas API REST para BootZen
 *
 * Este archivo define el controlador ApiRoutes, responsable de registrar rutas RESTful
 * para la API de la aplicación en el framework BootZen. Permite exponer endpoints
 * versionados, rutas con parámetros, rutas POST y agrupación de rutas bajo prefijos.
 *
 * @package    BootZen\Controllers
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.7
 * @since      1.0.0
 *
 * @see        BootZen\Core\Router
 */

declare(strict_types=1);

namespace BootZen\Controllers;

use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;

/**
 * Clase ApiRoutes
 *
 * Registra rutas RESTful para la API, incluyendo endpoints versionados, rutas con parámetros,
 * rutas POST y agrupación bajo prefijos. Facilita la extensión y mantenimiento de la API.
 *
 * - Soporta rutas GET y POST.
 * - Permite agrupación de rutas por prefijo.
 * - Ejemplo de uso de parámetros y respuestas JSON.
 *
 */
class ApiRoutes
{
    /**
     * Registra todas las rutas de la API en el router de BootZen.
     *
     * @param Router $router Instancia del router de BootZen
     * @return void
     *
     * @example
     *   $router = new Router();
     *   ApiRoutes::register($router);
     */
    public static function register(Router $router): void
    {
        // Ruta básica
        $router->get('/v1/', function (Request $request) {
            return Response::html('<h1>¡Bienvenido a BootZen!</h1>');
        });

        // Ruta con parámetros
        $router->get('/v1/user/{id}', function (Request $request, $id) {
            return Response::json([
                'user_id' => $id,
                'name' => 'Usuario ' . $id,
            ]);
        });

        // Ruta POST para API
        $router->post('/v1/api/users', function (Request $request) {
            $data = $request->getBody();

            return Response::json([
                'message' => 'Usuario creado',
                'data' => $data,
            ], 201);
        });

        // Grupo de rutas con prefijo
        $router->group(['prefix' => 'api/v1'], function ($router) {
            $router->get('/v1/posts', function (Request $request) {
                return Response::json([
                    'posts' => [
                        ['id' => 1, 'title' => 'Post 1'],
                        ['id' => 2, 'title' => 'Post 2'],
                    ],
                ]);
            });

            $router->get('/v1/posts/{id}', function (Request $request, $id) {
                return Response::json([
                    'post' => [
                        'id' => $id,
                        'title' => 'Post ' . $id,
                        'content' => 'Contenido del post ' . $id,
                    ],
                ]);
            });
        });
    }
}
