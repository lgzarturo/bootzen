<?php

/**
 * RateLimiter.php
 *
 * Cuenta intentos por clave en una ventana de tiempo, sobre la caché de archivos. Lo usan el middleware
 * throttle y el login para frenar ataques de fuerza bruta.
 *
 * @package    BootZen\Security
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Security;

use BootZen\Core\Cache;

/**
 * Limitador de intentos.
 *
 * @example
 *   $clave = 'login|' . $email . '|' . $request->getClientIp();
 *   if ($limiter->tooManyAttempts($clave, 5)) { ... $limiter->availableIn($clave) ... }
 *   $limiter->hit($clave, 60);
 */
final class RateLimiter
{
    public function __construct(private readonly Cache $cache)
    {
    }

    /**
     * Suma un intento. La ventana empieza con el primer intento y dura $decaySeconds.
     * Devuelve el total de intentos en la ventana.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        if (! $this->cache->has($this->timerKey($key))) {
            $this->cache->set($this->timerKey($key), time() + $decaySeconds, $decaySeconds);
            $this->cache->forget($this->counterKey($key));
        }

        return $this->cache->increment($this->counterKey($key), 1, $decaySeconds);
    }

    public function attempts(string $key): int
    {
        $attempts = $this->cache->get($this->counterKey($key), 0);

        return is_int($attempts) ? $attempts : 0;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->cache->has($this->timerKey($key)) && $this->attempts($key) >= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Segundos que faltan para que la ventana se reinicie.
     */
    public function availableIn(string $key): int
    {
        $resetAt = $this->cache->get($this->timerKey($key));

        return is_int($resetAt) ? max(0, $resetAt - time()) : 0;
    }

    /**
     * Reinicia los intentos (por ejemplo, después de un login correcto).
     */
    public function clear(string $key): void
    {
        $this->cache->forget($this->counterKey($key));
        $this->cache->forget($this->timerKey($key));
    }

    private function counterKey(string $key): string
    {
        return 'rate-limit:' . $key;
    }

    private function timerKey(string $key): string
    {
        return 'rate-limit:' . $key . ':timer';
    }
}
