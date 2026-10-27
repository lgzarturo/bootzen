<?php

/**
 * SecurityHeaders.php
 *
 * Cabeceras de seguridad para todas las respuestas: nosniff, X-Frame-Options, Referrer-Policy,
 * Permissions-Policy, HSTS en HTTPS y una Content-Security-Policy configurable en config/security.php.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Core\Config;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Agrega las cabeceras que la respuesta todavía no tenga (un controlador puede definir las suyas).
 */
final class SecurityHeaders implements MiddlewareInterface
{
    /**
     * CSP por defecto: todo desde el propio dominio, sin scripts en línea ni objetos embebidos.
     */
    public const DEFAULT_CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; "
        . "base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    public function __construct(private readonly Config $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);
        $csp = $this->config->get('security.csp', self::DEFAULT_CSP);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if (is_string($csp) && $csp !== '') {
            $headers['Content-Security-Policy'] = $csp;
        }

        if ($request->isSecure() && $this->config->get('security.hsts', true) !== false) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if ($response->getHeader($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
