<?php

/**
 * ReservedJob.php
 *
 * Job tomado de la tabla por un worker: su fila, la clase, los datos y el número de intento.
 *
 * @package    BootZen\Queue
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Queue;

/**
 * @internal Lo crean DatabaseQueue y Worker.
 */
final class ReservedJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $id,
        public readonly string $queue,
        public readonly string $class,
        public readonly array $payload,
        public readonly int $attempts
    ) {
    }
}
