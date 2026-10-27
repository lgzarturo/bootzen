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

use BootZen\Core\HttpException;
use BootZen\Core\ParameterizedMiddleware;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Security\RateLimiter;
use InvalidArgumentException;

/**
 * Middleware throttle:maximo,minutos (por defecto 60 peticiones por minuto).
 */
final class RateLimit implements ParameterizedMiddleware
{
    private int $maxAttempts = 60;

    private int $decayMinutes = 1;

    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function withParameters(array $parameters): static
    {
        $max = $parameters[0] ?? '60';
        $minutes = $parameters[1] ?? '1';

        if (! ctype_digit($max) || ! ctype_digit($minutes) || (int) $max < 1 || (int) $minutes < 1) {
            throw new InvalidArgumentException("throttle espera números positivos: throttle:60,1 (recibió {$max},{$minutes}).");
        }

        $clone = clone $this;
        $clone->maxAttempts = (int) $max;
        $clone->decayMinutes = (int) $minutes;

        return $clone;
    }

    public function process(Request $request, callable $next): Response
    {
        $key = sprintf('throttle|%d|%d|%s', $this->maxAttempts, $this->decayMinutes, $request->getClientIp());

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            $seconds = $this->limiter->availableIn($key);

            throw new HttpException(429, "Demasiadas peticiones. Intenta de nuevo en {$seconds} segundos.", [
                'Retry-After' => (string) $seconds,
                'X-RateLimit-Limit' => (string) $this->maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $this->limiter->hit($key, $this->decayMinutes * 60);
        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) $this->limiter->remaining($key, $this->maxAttempts),
        ]);
    }
}
