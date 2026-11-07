<?php

/**
 * Worker.php
 *
 * Atiende la cola de la base (php bin/bootzen queue:work): toma un job, llama a handle() con sus
 * dependencias del contenedor y lo borra; si falla lo reintenta más tarde o, al agotar los intentos,
 * lo pasa a failed_jobs y llama a failed().
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
use BootZen\Core\Logger;
use Closure;
use Throwable;

/**
 * Pensado para dos usos: desde cron (--stop-when-empty --max-time=50, cada minuto) o como proceso
 * permanente en un VPS (systemd o supervisor).
 */
final class Worker
{
    private readonly Closure $clock;

    private readonly Closure $sleep;

    /**
     * @param Closure(string): void $output Recibe una línea por job
     * @param (Closure(): float)|null $clock Segundos actuales (para los tests)
     * @param (Closure(int): void)|null $sleep Espera entre consultas a una cola vacía (para los tests)
     */
    public function __construct(
        private readonly DatabaseQueue $queue,
        private readonly Container $container,
        private readonly Closure $output,
        private readonly ?Logger $logger = null,
        ?Closure $clock = null,
        ?Closure $sleep = null
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
        $this->sleep = $sleep ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    /**
     * Procesa jobs hasta que se cumpla un límite. Devuelve cuántos procesó.
     *
     * @param bool $stopWhenEmpty Termina en cuanto no hay jobs pendientes
     * @param int $maxJobs Termina tras procesar esta cantidad (0 = sin límite)
     * @param int $maxTime Termina tras estos segundos (0 = sin límite); no corta un job a la mitad
     * @param int $sleep Segundos de espera cuando la cola está vacía
     */
    public function run(string $queue = 'default', bool $stopWhenEmpty = false, int $maxJobs = 0, int $maxTime = 0, int $sleep = 3): int
    {
        $start = ($this->clock)();
        $processed = 0;

        while (true) {
            if ($maxTime > 0 && ($this->clock)() - $start >= $maxTime) {
                break;
            }

            if (! $this->runNext($queue)) {
                if ($stopWhenEmpty) {
                    break;
                }

                ($this->sleep)($sleep);

                continue;
            }

            $processed++;

            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }

        return $processed;
    }

    /**
     * Procesa el siguiente job. Devuelve false si la cola estaba vacía.
     */
    public function runNext(string $queue = 'default'): bool
    {
        $reserved = $this->queue->pop($queue);

        if ($reserved === null) {
            return false;
        }

        $started = ($this->clock)();
        $label = "{$reserved->class} #{$reserved->id}";
        $job = null;

        try {
            $job = Job::fromPayload($reserved->class, $reserved->payload);
            $this->container->call([$job, 'handle']);
            $this->queue->delete($reserved);
            $this->line(sprintf('%s listo (%.2f s)', $label, ($this->clock)() - $started));
        } catch (Throwable $e) {
            $this->handleFailure($reserved, $job, $e, $label);
        }

        return true;
    }

    private function handleFailure(ReservedJob $reserved, ?Job $job, Throwable $error, string $label): void
    {
        $tries = $job !== null ? $job->tries : 1;

        if ($reserved->attempts < $tries) {
            $backoff = $job !== null ? $job->backoff : 0;
            $this->queue->release($reserved, $backoff);
            $this->line("{$label} falló (intento {$reserved->attempts} de {$tries}), se reintenta en {$backoff} s: {$error->getMessage()}");
            $this->logger?->warning("Job {$label} falló (intento {$reserved->attempts} de {$tries}): {$error->getMessage()}");

            return;
        }

        $this->queue->fail($reserved, $error);
        $this->line("{$label} falló definitivamente: {$error->getMessage()}");
        $this->logger?->error("Job {$label} pasó a failed_jobs tras {$reserved->attempts} intento(s)", ['exception' => $error]);

        try {
            $job?->failed($error);
        } catch (Throwable $e) {
            $this->logger?->error("failed() de {$label} lanzó una excepción", ['exception' => $e]);
        }
    }

    private function line(string $message): void
    {
        ($this->output)('[' . date('Y-m-d H:i:s') . "] {$message}");
    }
}
