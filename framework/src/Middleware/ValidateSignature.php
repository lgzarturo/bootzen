<?php

/**
 * ValidateSignature.php
 *
 * Middleware 'signed': la ruta solo responde a enlaces creados con signed_route() que no han vencido.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.6.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Core\HttpException;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Security\SignedUrl;

/**
 * Responde 403 si la firma no coincide (alguien cambió un parámetro) o el enlace venció.
 *
 * @example $router->get('/baja/{id}', [BajaController::class, 'show'])->name('baja')->middleware('signed');
 */
final class ValidateSignature implements MiddlewareInterface
{
    public function __construct(private readonly SignedUrl $signer)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (! $this->signer->check($request, time())) {
            throw new HttpException(403, 'El enlace no es válido o ya venció.');
        }

        return $next($request);
    }
}
