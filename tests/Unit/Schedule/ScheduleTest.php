<?php

declare(strict_types=1);

use BootZen\Core\Container;
use BootZen\Queue\Job;
use BootZen\Queue\Queue;
use BootZen\Schedule\CronExpression;
use BootZen\Schedule\Schedule;
use BootZen\Schedule\ScheduleRunner;
use BootZen\Schedule\Task;

final class ScheduleTestJob extends Job
{
    public function handle(): void
    {
    }
}

function at(string $time): DateTimeImmutable
{
    return new DateTimeImmutable($time, new DateTimeZone('America/Cancun'));
}

beforeEach(function (): void {
    $this->dir = makeTempDir('bootzen-schedule');
    $this->ran = [];
    $this->commands = [];
    $this->pushed = [];
    $this->now = at('2026-03-02 03:00');
    $this->output = [];

    $queue = new class ($this) implements Queue {
        public function __construct(private readonly object $test)
        {
        }

        public function push(Job $job, int $delay = 0): int
        {
            $this->test->pushed[] = $job::class;

            return 1;
        }
    };

    $this->schedule = new Schedule(
        new Container(),
        function (string $command): int {
            $this->commands[] = $command;

            return str_starts_with($command, 'falla') ? 1 : 0;
        },
        static fn (): Queue => $queue
    );
    $this->runner = fn (): ScheduleRunner => new ScheduleRunner($this->schedule, $this->dir, function (string $line): void {
        $this->output[] = $line;
    }, clock: fn (): DateTimeImmutable => $this->now);
});

afterEach(fn () => removeDir($this->dir));

it('interpreta expresiones cron', function (string $expression, string $time, bool $expected): void {
    expect((new CronExpression($expression))->matches(at($time)))->toBe($expected);
})->with([
    ['* * * * *', '2026-03-02 03:17', true],
    ['*/15 * * * *', '2026-03-02 03:30', true],
    ['*/15 * * * *', '2026-03-02 03:31', false],
    ['0 8-18/2 * * *', '2026-03-02 10:00', true],
    ['0 8-18/2 * * *', '2026-03-02 11:00', false],
    ['30 3 * * 1-5', '2026-03-02 03:30', true],
    ['30 3 * * 1-5', '2026-03-01 03:30', false],
    ['0 0 * * 7', '2026-03-01 00:00', true],
    ['0 0 1,15 * *', '2026-03-15 00:00', true],
    // Con día del mes y día de la semana restringidos basta con uno (como cron)
    ['0 0 13 * 5', '2026-03-13 00:00', true],
    ['0 0 13 * 5', '2026-03-06 00:00', true],
    ['0 0 13 * 5', '2026-03-07 00:00', false],
    ['0 9 * 12 *', '2026-03-02 09:00', false],
]);

it('calcula la próxima ejecución y rechaza expresiones inválidas', function (): void {
    expect((new CronExpression('30 3 * * 1-5'))->next(at('2026-03-06 04:00'))?->format('Y-m-d H:i'))->toBe('2026-03-09 03:30')
        ->and((new CronExpression('0 0 1 1 *'))->next(at('2026-03-02 10:00'))?->format('Y-m-d H:i'))->toBe('2027-01-01 00:00')
        ->and((new CronExpression('*/10 * * * *'))->next(at('2026-03-02 10:00'))?->format('H:i'))->toBe('10:10')
        ->and((new CronExpression('0 0 31 2 *'))->next(at('2026-03-02 10:00')))->toBeNull()
        ->and(fn () => new CronExpression('* * * *'))->toThrow(InvalidArgumentException::class, 'lleva 5 campos')
        ->and(fn () => new CronExpression('60 * * * *'))->toThrow(InvalidArgumentException::class, 'el minuto [60] debe estar entre 0 y 59')
        ->and(fn () => new CronExpression('* * * * lun'))->toThrow(InvalidArgumentException::class, 'día de la semana');
});

it('traduce las frecuencias a cron', function (Closure $set, string $expression): void {
    expect($set(new Task('x', static function (): void {
    }))->expression()->expression)->toBe($expression);
})->with([
    [fn (Task $t) => $t, '* * * * *'],
    [fn (Task $t) => $t->everyFiveMinutes(), '*/5 * * * *'],
    [fn (Task $t) => $t->everyFifteenMinutes(), '*/15 * * * *'],
    [fn (Task $t) => $t->hourlyAt(20), '20 * * * *'],
    [fn (Task $t) => $t->dailyAt('03:05'), '5 3 * * *'],
    [fn (Task $t) => $t->twiceDaily(9, 18), '0 9,18 * * *'],
    [fn (Task $t) => $t->weeklyOn(1, '08:30'), '30 8 * * 1'],
    [fn (Task $t) => $t->weekdaysAt('07:00'), '0 7 * * 1-5'],
    [fn (Task $t) => $t->monthlyOn(15, '12:00'), '0 12 15 * *'],
    [fn (Task $t) => $t->cron('0 */2 * * *'), '0 */2 * * *'],
]);

it('rechaza horas inválidas', function (): void {
    (new Task('x', static function (): void {
    }))->dailyAt('25:00');
})->throws(InvalidArgumentException::class, 'Hora inválida [25:00]');

it('ejecuta comandos, jobs y funciones a los que les toca', function (): void {
    $this->schedule->command('db:backup')->dailyAt('03:00');
    $this->schedule->command('queue:work --stop-when-empty')->everyMinute();
    $this->schedule->job(new ScheduleTestJob())->hourly();
    $this->schedule->call(function (Container $container): void {
        $this->ran[] = 'llamada';
    }, 'latido')->everyFifteenMinutes();
    $this->schedule->command('reporte')->dailyAt('08:00');

    expect(($this->runner)()->run())->toBe(0)
        ->and($this->commands)->toBe(['db:backup', 'queue:work --stop-when-empty'])
        ->and($this->pushed)->toBe([ScheduleTestJob::class])
        ->and($this->ran)->toBe(['llamada'])
        ->and($this->output[0])->toMatch('/^\[\d{4}-\d\d-\d\d \d\d:\d\d\] db:backup: listo \(\d+\.\d\d s\)$/');

    // En el mismo minuto no repite nada
    $this->commands = [];
    ($this->runner)()->run();

    expect($this->commands)->toBe([])
        ->and(end($this->output))->toContain('Ninguna tarea tocaba en este minuto.');
});

it('recupera lo que tocaba entre dos vueltas de un cron cada 15 minutos', function (): void {
    $this->schedule->command('a-las-03-07')->dailyAt('03:07');
    $this->schedule->command('cada-minuto')->everyMinute();

    ($this->runner)()->run();
    $this->now = at('2026-03-02 03:15');
    ($this->runner)()->run();

    // a-las-03-07 corre una vez en la vuelta de 03:15; cada-minuto una vez por vuelta, no 15
    expect($this->commands)->toBe(['cada-minuto', 'a-las-03-07', 'cada-minuto']);

    // Una tarea nueva no recupera el pasado: la primera vez solo mira el minuto actual
    $this->schedule->command('nueva')->dailyAt('03:10');
    $this->now = at('2026-03-02 03:16');
    $this->commands = [];
    ($this->runner)()->run();

    expect($this->commands)->toBe(['cada-minuto']);
});

it('sigue con las demás si una falla y lo cuenta', function (): void {
    $this->schedule->command('falla:siempre');
    $this->schedule->call(function (): void {
        throw new RuntimeException('sin conexión');
    }, 'sincronizar');
    $this->schedule->command('db:backup');

    expect(($this->runner)()->run())->toBe(2)
        ->and($this->commands)->toBe(['falla:siempre', 'db:backup'])
        ->and(implode("\n", $this->output))->toContain('falla:siempre: falló: El comando falla:siempre terminó con código 1.')
        ->toContain('sincronizar: falló: sin conexión');
});

it('salta una tarea que sigue corriendo y guarda la última vuelta', function (): void {
    $task = $this->schedule->command('lenta');
    $lock = fopen($this->dir . '/schedule-' . substr($task->key(), 0, 16) . '.lock', 'c');
    flock($lock, LOCK_EX);

    $runner = ($this->runner)();
    $runner->run();
    flock($lock, LOCK_UN);
    fclose($lock);

    expect($this->commands)->toBe([])
        ->and($this->output[0])->toContain('lenta: sigue corriendo desde la vuelta anterior, se salta.')
        ->and($runner->lastRun())->toBe(at('2026-03-02 03:00')->getTimestamp());
});
