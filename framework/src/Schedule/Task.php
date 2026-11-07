<?php

/**
 * Task.php
 *
 * Tarea programada de routes/schedule.php: qué se ejecuta y cada cuánto (everyFiveMinutes(),
 * dailyAt('03:00'), weeklyOn(1, '08:00'), cron('*\/10 8-18 * * 1-5')...).
 *
 * @package    BootZen\Schedule
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Schedule;

use Closure;
use InvalidArgumentException;

/**
 * Sin frecuencia corre cada minuto. Las horas son de APP_TIMEZONE.
 *
 * @example $schedule->command('db:backup')->dailyAt('03:00');
 */
final class Task
{
    private CronExpression $cron;

    /**
     * @param Closure(): void $callback
     */
    public function __construct(private string $description, private readonly Closure $callback)
    {
        $this->cron = new CronExpression('* * * * *');
    }

    /**
     * Cada minuto (lo de siempre si no se indica otra frecuencia).
     */
    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    /**
     * Cada 5 minutos.
     */
    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Cada 10 minutos.
     */
    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    /**
     * Cada 15 minutos.
     */
    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    /**
     * Cada 30 minutos.
     */
    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    /**
     * Cada hora en punto.
     */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Cada hora en el minuto indicado.
     */
    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    /**
     * Todos los días a medianoche.
     */
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /**
     * Todos los días a la hora indicada ('03:00').
     */
    public function dailyAt(string $time): self
    {
        [$hour, $minute] = self::time($time);

        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Todos los días a dos horas en punto (por ejemplo 9 y 18).
     */
    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    /**
     * Los domingos a medianoche.
     */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Un día de la semana (0 domingo, 1 lunes... 6 sábado) a la hora indicada.
     */
    public function weeklyOn(int $day, string $time = '00:00'): self
    {
        [$hour, $minute] = self::time($time);

        return $this->cron("{$minute} {$hour} * * {$day}");
    }

    /**
     * De lunes a viernes a la hora indicada.
     */
    public function weekdaysAt(string $time): self
    {
        [$hour, $minute] = self::time($time);

        return $this->cron("{$minute} {$hour} * * 1-5");
    }

    /**
     * El día 1 de cada mes a medianoche.
     */
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Un día del mes a la hora indicada.
     */
    public function monthlyOn(int $day, string $time = '00:00'): self
    {
        [$hour, $minute] = self::time($time);

        return $this->cron("{$minute} {$hour} {$day} * *");
    }

    /**
     * Expresión cron de 5 campos.
     *
     * @throws InvalidArgumentException Si la expresión no es válida
     */
    public function cron(string $expression): self
    {
        $this->cron = new CronExpression($expression);

        return $this;
    }

    /**
     * Nombre para la salida de schedule:run y schedule:list.
     */
    public function name(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function expression(): CronExpression
    {
        return $this->cron;
    }

    /**
     * Identificador estable para guardar cuándo corrió: cambia si cambia el nombre o la frecuencia.
     *
     * @internal Lo usa ScheduleRunner.
     */
    public function key(): string
    {
        return sha1($this->description . '|' . $this->cron->expression);
    }

    /**
     * Ejecuta la tarea.
     *
     * @internal Lo llama ScheduleRunner.
     */
    public function run(): void
    {
        ($this->callback)();
    }

    /**
     * @return array{int, int}
     */
    private static function time(string $time): array
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $match) !== 1) {
            throw new InvalidArgumentException("Hora inválida [{$time}]: usa HH:MM, por ejemplo 03:00.");
        }

        return [(int) $match[1], (int) $match[2]];
    }
}
