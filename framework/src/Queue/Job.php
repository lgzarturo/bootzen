<?php

/**
 * Job.php
 *
 * Trabajo para ejecutar en segundo plano: se encola con dispatch(new MiJob(...)) y un worker
 * (php bin/bootzen queue:work) llama a handle(). Los datos viajan como JSON: los parámetros del
 * constructor deben ser propiedades públicas promovidas con valores simples (int, string, bool,
 * float, null o arrays de ellos). Pasa ids, no modelos.
 *
 * @package    BootZen\Queue
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Queue;

use InvalidArgumentException;
use ReflectionClass;
use Throwable;

/**
 * handle() recibe sus dependencias del contenedor (por ejemplo Mailer o Connection). Si lanza una
 * excepción, se reintenta $tries veces esperando $backoff segundos; después pasa a los fallidos y se
 * llama a failed().
 *
 * @example dispatch(new EnviarFactura($factura->id));
 */
abstract class Job
{
    /**
     * Intentos antes de darlo por fallido.
     */
    public int $tries = 3;

    /**
     * Segundos de espera antes de reintentar.
     */
    public int $backoff = 60;

    /**
     * Cola en la que se guarda (queue:work --queue=nombre la atiende).
     */
    public string $queue = 'default';

    /**
     * Se llama una sola vez, cuando el job agotó sus intentos.
     */
    public function failed(Throwable $exception): void
    {
    }

    /**
     * Datos para guardar en la cola: los parámetros del constructor, leídos de sus propiedades.
     *
     * @internal Lo usan las colas.
     * @return array<string, mixed>
     * @throws InvalidArgumentException Si un parámetro no es una propiedad pública o su valor no cabe en JSON
     */
    final public function toPayload(): array
    {
        $payload = [];
        $constructor = (new ReflectionClass($this))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();

            if (! property_exists($this, $name) || ! (new \ReflectionProperty($this, $name))->isPublic()) {
                throw new InvalidArgumentException(sprintf(
                    'El parámetro $%s de %s debe ser una propiedad pública: escríbelo como public readonly en el constructor.',
                    $name,
                    static::class
                ));
            }

            $value = $this->{$name};

            if (! self::serializable($value)) {
                throw new InvalidArgumentException(sprintf(
                    'El parámetro $%s de %s no se puede guardar en la cola: usa valores simples (por ejemplo el id, no el modelo).',
                    $name,
                    static::class
                ));
            }

            $payload[$name] = $value;
        }

        return $payload;
    }

    /**
     * Reconstruye el job desde la cola.
     *
     * @internal Lo usan las colas.
     * @param array<string, mixed> $payload
     * @throws InvalidArgumentException Si la clase no existe o no es un Job
     */
    final public static function fromPayload(string $class, array $payload): self
    {
        if (! class_exists($class) || ! is_subclass_of($class, self::class)) {
            throw new InvalidArgumentException("La clase {$class} no existe o no es un Job.");
        }

        return (new ReflectionClass($class))->newInstanceArgs($payload);
    }

    private static function serializable(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::serializable($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }
}
