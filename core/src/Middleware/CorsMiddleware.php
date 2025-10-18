<?php

/**
 * Middleware de Control de Acceso CORS para BootZen
 *
 * Este archivo define el middleware responsable de gestionar las cabeceras CORS (Cross-Origin Resource Sharing)
 * en el framework BootZen. Permite controlar el acceso de orígenes cruzados, configurando políticas de seguridad
 * y compatibilidad para peticiones HTTP entre dominios. Implementa el patrón Chain of Responsibility y sigue
 * el contrato de MiddlewareInterface.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.6
 * @since      1.0.0
 * *
 * @see        https://developer.mozilla.org/es/docs/Web/HTTP/CORS
 * @see        BootZen\Core\MiddlewareInterface
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Clase CorsMiddleware
 *
 * Gestiona las cabeceras CORS para las respuestas HTTP, permitiendo o restringiendo el acceso
 * a recursos desde orígenes externos según la configuración definida. Es esencial para la seguridad
 * y la interoperabilidad de APIs RESTful en BootZen.
 *
 * - Permite configurar orígenes, métodos, cabeceras y credenciales permitidas.
 * - Procesa solicitudes preflight (OPTIONS) y añade cabeceras CORS a todas las respuestas.
 * - Implementa el patrón Chain of Responsibility.
 *
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Configuración de CORS para el middleware.
     *
     * - allowed_origins: Lista de orígenes permitidos.
     * - allowed_methods: Métodos HTTP permitidos.
     * - allowed_headers: Cabeceras permitidas en la solicitud.
     * - exposed_headers: Cabeceras expuestas al cliente.
     * - max_age: Tiempo de cacheo de la preflight (en segundos).
     * - credentials: Si se permiten credenciales (cookies, auth).
     *
     * @var array<string, mixed> Configuración de CORS
     */
    private array $config;

    /**
     * Constructor de CorsMiddleware
     *
     * Inicializa la configuración de CORS con valores por defecto o personalizados.
     *
     * @param array<string, mixed> $config Configuración personalizada de CORS
     *
     * @example
     *   $middleware = new CorsMiddleware([
     *       'allowed_origins' => ['https://dominio.com'],
     *       'credentials' => true
     *   ]);
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token'],
            'exposed_headers' => [],
            'max_age' => 86400,
            'credentials' => false,
        ], $config);
    }

    /**
     * Procesa la solicitud HTTP y aplica las cabeceras CORS correspondientes.
     *
     * Si la solicitud es de tipo OPTIONS (preflight), responde inmediatamente con las cabeceras CORS.
     * En otros casos, delega la solicitud al siguiente middleware y añade las cabeceras CORS a la respuesta.
     *
     * @param Request $request Instancia de la solicitud HTTP
     * @param callable $next Siguiente middleware o controlador
     * @return Response Respuesta HTTP con cabeceras CORS
     *
     * @example
     *   // En el pipeline de BootZen:
     *   $app->addMiddleware(new CorsMiddleware());
     */
    public function process(Request $request, callable $next): Response
    {
        $origin = $request->getHeader('origin');

        // Gestiona solicitudes preflight OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflight($request, $origin);
        }

        // Procesa la solicitud y añade cabeceras CORS
        $response = $next($request);

        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Maneja solicitudes preflight (OPTIONS) añadiendo cabeceras CORS específicas.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param Request $request Solicitud HTTP OPTIONS
     * @param string|null $origin Origen de la solicitud
     * @return Response Respuesta HTTP con cabeceras preflight
     *
     * @example
     *   // Ejemplo de respuesta preflight
     *   $response = $middleware->handlePreflight($request, 'https://dominio.com');
     */
    private function handlePreflight(Request $request, ?string $origin): Response
    {
        $response = new Response(200);

        if ($this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin ?? '*');
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->config['allowed_headers']))
            ->withHeader('Access-Control-Max-Age', (string) $this->config['max_age']);

        if ($this->config['credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Añade cabeceras CORS a la respuesta HTTP.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param Response $response Respuesta HTTP original
     * @param string|null $origin Origen de la solicitud
     * @return Response Respuesta HTTP con cabeceras CORS
     */
    private function addCorsHeaders(Response $response, ?string $origin): Response
    {
        if ($this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin ?? '*');
        }

        if (! empty($this->config['exposed_headers'])) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers'])
            );
        }

        if ($this->config['credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Valida si el origen de la solicitud está permitido según la configuración.
     *
     * Considera la política de credenciales y el uso de comodines.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param string|null $origin Origen de la solicitud
     * @return bool True si el origen está permitido, false en caso contrario
     *
     * @todo Permitir validaciones personalizadas de origen mediante callback
     */
    private function isOriginAllowed(?string $origin): bool
    {
        // Si las credenciales están habilitadas, no se permite el comodín '*'
        if ($this->config['credentials']) {
            return $origin && in_array($origin, $this->config['allowed_origins'], true);
        }

        if (in_array('*', $this->config['allowed_origins'])) {
            return true;
        }

        return $origin && in_array($origin, $this->config['allowed_origins'], true);
    }
}
