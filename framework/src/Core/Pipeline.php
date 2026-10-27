<?php

/**
 * Pipeline.php
 *
 * Ejecuta cadenas de middleware. Lo comparten el middleware global de Application y el de cada ruta,
 * así ambos aceptan exactamente lo mismo: clases, instancias, alias ('auth') y grupos ('web').
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use Closure;
use InvalidArgumentException;

/**
 * Pipeline de middleware con alias y grupos.
 *
 * @example $pipeline->alias('cors', CorsMiddleware::class);
 * @example $pipeline->group('api', ['cors']);
 * @example ->middleware('throttle:60,1') pasa ['60', '1'] a un ParameterizedMiddleware
 */
final class Pipeline
{
    /**
     * Alias de middleware: nombre => clase.
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Grupos de middleware: nombre => lista de middleware.
     *
     * @var array<string, list<string|MiddlewareInterface>>
     */
    private array $groups = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Registra un alias para una clase de middleware.
     */
    public function alias(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    /**
     * Registra un grupo de middleware. Puede contener clases, alias u otros grupos.
     *
     * @param list<string|MiddlewareInterface> $middleware
     */
    public function group(string $name, array $middleware): void
    {
        $this->groups[$name] = $middleware;
    }

    /**
     * Ejecuta el middleware en orden y finalmente el destino.
     *
     * @param list<string|MiddlewareInterface> $middleware
     * @param Closure(Request): Response $destination
     * @throws InvalidArgumentException Si un middleware no existe o no implementa MiddlewareInterface
     */
    public function run(array $middleware, Request $request, Closure $destination): Response
    {
        $next = $destination;

        foreach (array_reverse($this->expand($middleware)) as $entry) {
            $next = function (Request $request) use ($entry, $next): Response {
                return $this->instantiate($entry)->process($request, $next);
            };
        }

        return $next($request);
    }

    /**
     * Expande grupos y alias a una lista plana de clases o instancias.
     *
     * @param list<string|MiddlewareInterface> $middleware
     * @param list<string> $visiting Grupos en expansión, para detectar ciclos
     * @return list<string|MiddlewareInterface>
     * @throws InvalidArgumentException Si un grupo se incluye a sí mismo
     */
    public function expand(array $middleware, array $visiting = []): array
    {
        $expanded = [];

        foreach ($middleware as $entry) {
            if (is_string($entry) && str_contains($entry, ':')) {
                // 'alias:param1,param2': se resuelve el alias y se conservan los parámetros
                [$name, $parameters] = explode(':', $entry, 2);
                $expanded[] = ($this->aliases[$name] ?? $name) . ':' . $parameters;

                continue;
            }

            if (is_string($entry) && isset($this->groups[$entry])) {
                if (in_array($entry, $visiting, true)) {
                    throw new InvalidArgumentException("El grupo de middleware [{$entry}] se incluye a sí mismo.");
                }

                array_push($expanded, ...$this->expand($this->groups[$entry], [...$visiting, $entry]));

                continue;
            }

            $expanded[] = is_string($entry) ? ($this->aliases[$entry] ?? $entry) : $entry;
        }

        return $expanded;
    }

    /**
     * @throws InvalidArgumentException Si el middleware no existe o no implementa MiddlewareInterface
     */
    private function instantiate(string|MiddlewareInterface $entry): MiddlewareInterface
    {
        if ($entry instanceof MiddlewareInterface) {
            return $entry;
        }

        $parameters = null;
        if (str_contains($entry, ':')) {
            [$entry, $parameters] = explode(':', $entry, 2);
        }

        if (! class_exists($entry)) {
            throw new InvalidArgumentException(
                "Middleware [{$entry}] desconocido: no es una clase, un alias ni un grupo registrado."
            );
        }

        $instance = $this->container->make($entry);

        if (! $instance instanceof MiddlewareInterface) {
            throw new InvalidArgumentException("El middleware [{$entry}] debe implementar MiddlewareInterface.");
        }

        if ($parameters === null) {
            return $instance;
        }

        if (! $instance instanceof ParameterizedMiddleware) {
            throw new InvalidArgumentException("El middleware [{$entry}] no acepta parámetros.");
        }

        return $instance->withParameters(explode(',', $parameters));
    }
}
