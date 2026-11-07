<?php

/**
 * ScheduleRunner.php
 *
 * Lo que hace php bin/bootzen schedule:run: ejecuta las tareas a las que les tocó desde la última
 * vez, una tras otra, y guarda cuándo corrió cada una en storage/cache/schedule.json.
 *
 * @package    BootZen\Schedule
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Schedule;

use BootZen\Core\Logger;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Si el cron corre cada 5 o 15 minutos, o se saltó una vuelta, la tarea que tocaba en medio corre en
 * la siguiente (una sola vez). Lo atrasado de más de un día no se recupera. Una tarea que sigue
 * corriendo desde una vuelta anterior se salta.
 */
final class ScheduleRunner
{
    private const CATCH_UP_MINUTES = 1440;

    private readonly Closure $clock;

    /**
     * @param Closure(string): void $output
     * @param (Closure(): DateTimeImmutable)|null $clock Hora actual (para los tests)
     */
    public function __construct(
        private readonly Schedule $schedule,
        private readonly string $directory,
        private readonly Closure $output,
        private readonly ?Logger $logger = null,
        ?Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    }

    /**
     * Ejecuta las tareas pendientes. Devuelve cuántas fallaron.
     */
    public function run(): int
    {
        $now = $this->minute(($this->clock)());
        $failures = 0;
        $ran = 0;

        foreach ($this->schedule->tasks() as $task) {
            // Desde la última vuelta de schedule:run o la última vez que corrió esta tarea (lo más reciente).
            // La primera vez de todas solo cuenta el minuto actual
            $state = $this->state();
            $since = max($state['last_run'] ?? $now->getTimestamp() - 60, $state['tasks'][$task->key()] ?? 0);

            if (! $this->dueBetween($task->expression(), $since, $now)) {
                continue;
            }

            $lock = $this->lock($task);

            if ($lock === null) {
                $this->line("{$task->description()}: sigue corriendo desde la vuelta anterior, se salta.");

                continue;
            }

            try {
                $this->remember(['tasks' => [$task->key() => $now->getTimestamp()]]);
                $started = microtime(true);
                $task->run();
                $ran++;
                $this->line(sprintf('%s: listo (%.2f s)', $task->description(), microtime(true) - $started));
            } catch (Throwable $e) {
                $failures++;
                $this->line("{$task->description()}: falló: {$e->getMessage()}");
                $this->logger?->error("La tarea programada {$task->description()} falló", ['exception' => $e]);
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $this->remember(['last_run' => $now->getTimestamp()]);

        if ($ran === 0 && $failures === 0) {
            $this->line('Ninguna tarea tocaba en este minuto.');
        }

        return $failures;
    }

    /**
     * Momento de la última vez que corrió schedule:run, o null si nunca.
     */
    public function lastRun(): ?int
    {
        return $this->state()['last_run'] ?? null;
    }

    /**
     * ¿Le tocó a la expresión algún minuto después de $since y hasta $now (incluido)?
     */
    private function dueBetween(CronExpression $cron, int $since, DateTimeImmutable $now): bool
    {
        $minutes = min(self::CATCH_UP_MINUTES, intdiv($now->getTimestamp() - $since, 60));

        for ($i = 0; $i < $minutes; $i++) {
            if ($cron->matches($now->modify("-{$i} minutes"))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return resource|null
     */
    private function lock(Task $task)
    {
        $this->ensureDirectory();
        $handle = fopen($this->directory . '/schedule-' . substr($task->key(), 0, 16) . '.lock', 'c');

        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * @return array{last_run?: int, tasks?: array<string, int>}
     */
    private function state(): array
    {
        $file = $this->directory . '/schedule.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $state = [];

        if (is_array($data) && is_int($data['last_run'] ?? null)) {
            $state['last_run'] = $data['last_run'];
        }

        if (is_array($data) && is_array($data['tasks'] ?? null)) {
            $state['tasks'] = array_filter($data['tasks'], 'is_int');
        }

        return $state;
    }

    /**
     * Mezcla y guarda el estado con bloqueo, por si otra vuelta escribe al mismo tiempo.
     *
     * @param array{last_run?: int, tasks?: array<string, int>} $changes
     */
    private function remember(array $changes): void
    {
        $this->ensureDirectory();
        $handle = fopen($this->directory . '/schedule.json', 'c+');

        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);
        $current = json_decode((string) stream_get_contents($handle), true);
        $state = is_array($current) ? $current : [];
        $state['tasks'] = [...($state['tasks'] ?? []), ...($changes['tasks'] ?? [])];

        if (isset($changes['last_run'])) {
            $state['last_run'] = $changes['last_run'];
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($state, JSON_PRETTY_PRINT));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function minute(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTime((int) $time->format('G'), (int) $time->format('i'));
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    private function line(string $message): void
    {
        ($this->output)('[' . date('Y-m-d H:i') . "] {$message}");
    }
}
