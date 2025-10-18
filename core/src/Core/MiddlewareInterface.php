<?php

/**
 * MiddlewareInterface.php
 *
 * Define el contrato para los middlewares HTTP en el framework BootZen.
 * Permite la implementación de lógica de procesamiento previa o posterior a la ejecución de controladores,
 * facilitando la extensión y modularidad del pipeline de peticiones.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.8
 * @since      1.0.0
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

/**
 * Interfaz MiddlewareInterface
 *
 * Contrato para middlewares HTTP en BootZen. Permite interceptar, modificar o rechazar peticiones/respuestas
 * antes o después de los controladores. Implementa el patrón Chain of Responsibility.
 *
 * Consideraciones de seguridad: los middlewares pueden validar autenticación, autorización y sanitizar datos.
 * Consideraciones de rendimiento: permite cachear respuestas o limitar acceso a recursos costosos.
 *
 */
interface MiddlewareInterface
{
    /**
     * Procesa la petición HTTP y delega al siguiente middleware/controlador.
     *
     * @param Request $request Objeto de la petición HTTP
     * @param callable $next Callback para invocar el siguiente middleware/controlador
     * @return Response Respuesta HTTP generada
     * @throws \Exception Puede lanzar excepciones de validación, autenticación o errores de aplicación
     * @example
     *   $response = $middleware->process($request, function($req) {
     *       // lógica del siguiente middleware/controlador
     *       return new Response('OK');
     *   });
     */
    public function process(Request $request, callable $next): Response;
}
