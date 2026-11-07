<?php

/**
 * SyncQueue.php
 *
 * Driver 'sync' (QUEUE_DRIVER=sync): ejecuta el job en el momento, dentro de la misma petición.
 * Sin tabla ni worker. Es el driver por defecto y el de los tests.
 *
 * @package    BootZen\Queue
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Queue;

use BootZen\Core\Container;

/**
 * Ignora el retraso y los reintentos: si handle() falla, la excepción llega a quien hizo dispatch().
 * Pasa el job por toPayload() y fromPayload() igual que la cola real, así un job que no se puede
 * guardar falla también en los tests.
 */
final class SyncQueue implements Queue
{
    public function __construct(private readonly Container $container)
    {
    }

    public function push(Job $job, int $delay = 0): int
    {
        $payload = json_decode((string) json_encode($job->toPayload()), true);
        $copy = Job::fromPayload($job::class, is_array($payload) ? $payload : []);
        $this->container->call([$copy, 'handle']);

        return 0;
    }
}
