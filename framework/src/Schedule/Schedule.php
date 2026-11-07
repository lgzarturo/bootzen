<?php

/**
 * Schedule.php
 *
 * Lista de tareas programadas que define routes/schedule.php. Las ejecuta php bin/bootzen schedule:run,
 * que se llama desde una sola línea de cron cada minuto (o cada 5 o 15 en hostings que no permiten menos).
 *
 * @package    BootZen\Schedule
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Schedule;

use BootZen\Core\Container;
use BootZen\Queue\Job;
use BootZen\Queue\Queue;
use Closure;
use RuntimeException;

/**
 * @example $schedule->command('db:backup')->dailyAt('03:00'); $schedule->job(new LimpiarCarritos())->hourly();
 */
final class Schedule
{
    /**
     * @var list<Task>
     */
    private array $tasks = [];

    /**
     * @param Closure(string): int $console Ejecuta un comando de bin/bootzen y devuelve su código de salida
     * @param Closure(): Queue $queue
     */
    public function __construct(
        private readonly Container $container,
        private readonly Closure $console,
        private readonly Closure $queue
    ) {
    }

    /**
     * Comando de bin/bootzen, con sus opciones: 'db:backup', 'queue:work --stop-when-empty'.
     */
    public function command(string $command): Task
    {
        return $this->add($command, function () use ($command): void {
            $status = ($this->console)($command);

            if ($status !== 0) {
                throw new RuntimeException("El comando {$command} terminó con código {$status}.");
            }
        });
    }

    /**
     * Encola un job (con QUEUE_DRIVER=sync se ejecuta en el momento).
     */
    public function job(Job $job): Task
    {
        return $this->add($job::class, function () use ($job): void {
            ($this->queue)()->push($job);
        });
    }

    /**
     * Función propia; recibe sus dependencias del contenedor.
     */
    public function call(Closure $callback, string $name): Task
    {
        return $this->add($name, function () use ($callback): void {
            $this->container->call($callback);
        });
    }

    /**
     * Tareas definidas, en orden.
     *
     * @internal Lo usan schedule:run, schedule:list y doctor.
     * @return list<Task>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /**
     * @param Closure(): void $callback
     */
    private function add(string $description, Closure $callback): Task
    {
        $task = new Task($description, $callback);
        $this->tasks[] = $task;

        return $task;
    }
}
