<?php

/**
 * RateLimit.php
 *
 * Limita las peticiones por IP: ->middleware('throttle:60,1') permite 60 peticiones por minuto.
 * Al pasarse responde 429 con la cabecera Retry-After.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Core\Application;
use BootZen\Core\HttpException;
use BootZen\Core\ParameterizedMiddleware;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Security\RateLimiter;
use InvalidArgumentException;

/**
 * Middleware throttle:maximo,minutos (por defecto 60 peticiones por minuto) por IP. Un tercer parámetro le
 * da cupo propio: throttle:5,1,login no comparte contador con otras rutas que usen throttle:5,1.
 */
final class RateLimit implements ParameterizedMiddleware
{
    private int $maxAttempts = 60;

    private int $decayMinutes = 1;

    private string $name = '';

    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function withParameters(array $parameters): static
    {
        $max = $parameters[0] ?? '60';
        $minutes = $parameters[1] ?? '1';
        $name = $parameters[2] ?? '';

        if (preg_match('/^[A-Za-z0-9_.-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("El nombre del límite solo lleva letras, números, puntos y guiones: throttle:5,1,login (recibió {$name}).");
        }

        if (! ctype_digit($max) || ! ctype_digit($minutes) || (int) $max < 1 || (int) $minutes < 1) {
            throw new InvalidArgumentException("throttle espera números positivos: throttle:60,1 (recibió {$max},{$minutes}).");
        }

        $clone = clone $this;
        $clone->maxAttempts = (int) $max;
        $clone->decayMinutes = (int) $minutes;
        $clone->name = $name;

        return $clone;
    }

    public function process(Request $request, callable $next): Response
    {
        // Sin nombre, las rutas con el mismo throttle:5,1 comparten cupo por IP; con nombre cada una tiene el suyo
        $key = sprintf('throttle|%d|%d|%s|%s', $this->maxAttempts, $this->decayMinutes, $this->name, $request->getClientIp());

        if (! $this->limiter->attempt($key, $this->maxAttempts, $this->decayMinutes * 60)) {
            $seconds = $this->limiter->availableIn($key);

            $message = 'Demasiadas peticiones. Intenta de nuevo en :seconds segundos.';
            $message = Application::hasInstance() && function_exists('__') ? __($message, ['seconds' => $seconds]) : str_replace(':seconds', (string) $seconds, $message);

            throw new HttpException(429, $message, [
                'Retry-After' => (string) $seconds,
                'X-RateLimit-Limit' => (string) $this->maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) $this->limiter->remaining($key, $this->maxAttempts),
        ]);
    }
}
