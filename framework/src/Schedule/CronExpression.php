<?php

/**
 * CronExpression.php
 *
 * Expresión cron de 5 campos (minuto hora día-del-mes mes día-de-la-semana) con *, números, rangos
 * (1-5), pasos (*\/15, 8-18/2) y listas (1,15). El domingo es 0 o 7.
 *
 * @package    BootZen\Schedule
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Schedule;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Como en cron: si se restringen el día del mes y el día de la semana a la vez, basta con que coincida uno.
 *
 * @example $cron->matches($ahora); // con new CronExpression('30 3 * * 1-5'): 3:30 de lunes a viernes
 */
final class CronExpression
{
    private const LIMITS = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];

    private const NAMES = ['minuto', 'hora', 'día del mes', 'mes', 'día de la semana'];

    /**
     * Valores permitidos por campo.
     *
     * @var list<array<int, true>>
     */
    private array $fields = [];

    private bool $anyDayOfMonth;

    private bool $anyDayOfWeek;

    /**
     * @throws InvalidArgumentException Si la expresión no tiene 5 campos válidos
     */
    public function __construct(public readonly string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];

        if (count($parts) !== 5) {
            throw new InvalidArgumentException("Expresión cron inválida [{$expression}]: lleva 5 campos (minuto hora día mes día-de-la-semana).");
        }

        foreach ($parts as $index => $part) {
            $this->fields[] = $this->parse($part, $index);
        }

        // El 7 también es domingo
        if (isset($this->fields[4][7])) {
            $this->fields[4][0] = true;
        }

        $this->anyDayOfMonth = $parts[2] === '*';
        $this->anyDayOfWeek = $parts[4] === '*';
    }

    /**
     * ¿Le toca en este minuto?
     */
    public function matches(DateTimeInterface $time): bool
    {
        [$minute, $hour, $day, $month, $weekday] = array_map('intval', explode(' ', $time->format('i G j n w')));

        if (! isset($this->fields[0][$minute], $this->fields[1][$hour], $this->fields[3][$month])) {
            return false;
        }

        $dayOfMonth = isset($this->fields[2][$day]);
        $dayOfWeek = isset($this->fields[4][$weekday]);

        if (! $this->anyDayOfMonth && ! $this->anyDayOfWeek) {
            return $dayOfMonth || $dayOfWeek;
        }

        return $dayOfMonth && $dayOfWeek;
    }

    /**
     * Próximo minuto en que le toca, después de $from. Null si no hay ninguno en los próximos 400 días.
     */
    public function next(DateTimeImmutable $from): ?DateTimeImmutable
    {
        $minute = new DateInterval('PT1M');
        $time = $from->setTime((int) $from->format('G'), (int) $from->format('i'))->add($minute);
        $limit = $from->modify('+400 days');

        while ($time <= $limit) {
            if (! isset($this->fields[3][(int) $time->format('n')])) {
                $time = $time->modify('first day of next month')->setTime(0, 0);

                continue;
            }

            if (! $this->dayMatches($time)) {
                $time = $time->modify('+1 day')->setTime(0, 0);

                continue;
            }

            if (! isset($this->fields[1][(int) $time->format('G')])) {
                $time = $time->setTime((int) $time->format('G'), 0)->modify('+1 hour');

                continue;
            }

            if ($this->matches($time)) {
                return $time;
            }

            $time = $time->add($minute);
        }

        return null;
    }

    private function dayMatches(DateTimeInterface $time): bool
    {
        $dayOfMonth = isset($this->fields[2][(int) $time->format('j')]);
        $dayOfWeek = isset($this->fields[4][(int) $time->format('w')]);

        return ! $this->anyDayOfMonth && ! $this->anyDayOfWeek ? $dayOfMonth || $dayOfWeek : $dayOfMonth && $dayOfWeek;
    }

    /**
     * @return array<int, true>
     */
    private function parse(string $field, int $index): array
    {
        [$min, $max] = self::LIMITS[$index];
        $values = [];

        foreach (explode(',', $field) as $item) {
            if (preg_match('/^(\*|\d+(?:-\d+)?)(?:\/(\d+))?$/', $item, $match) !== 1) {
                throw $this->invalid($field, $index);
            }

            $step = isset($match[2]) ? (int) $match[2] : 1;

            if ($match[1] === '*') {
                [$from, $to] = [$min, $max];
            } elseif (str_contains($match[1], '-')) {
                [$from, $to] = array_map('intval', explode('-', $match[1]));
            } else {
                $from = (int) $match[1];
                $to = isset($match[2]) ? $max : $from;
            }

            if ($step < 1 || $from < $min || $to > $max || $from > $to) {
                throw $this->invalid($field, $index);
            }

            for ($value = $from; $value <= $to; $value += $step) {
                $values[$value] = true;
            }
        }

        return $values;
    }

    private function invalid(string $field, int $index): InvalidArgumentException
    {
        [$min, $max] = self::LIMITS[$index];

        return new InvalidArgumentException(sprintf(
            'Expresión cron inválida [%s]: el %s [%s] debe estar entre %d y %d.',
            $this->expression,
            self::NAMES[$index],
            $field,
            $min,
            $max
        ));
    }
}
