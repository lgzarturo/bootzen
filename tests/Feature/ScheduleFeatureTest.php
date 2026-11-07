<?php

declare(strict_types=1);

use BootZen\Console\Console;
use BootZen\Core\Application;
use BootZen\Frontend\Tailwind;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-schedule-app');
    writeFile($this->project . '/config/app.php', "<?php return ['name' => 'Tienda', 'timezone' => 'America/Mexico_City'];");
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]];");
    writeFile($this->project . '/public/assets/app.css', '');
    $this->output = fopen('php://memory', 'w+');
    $this->run = function (string ...$argv): int {
        return (new Console(new Application($this->project), new Tailwind($this->project, 'Linux', 'x86_64'), $this->output))->run(['bootzen', ...$argv]);
    };
});

afterEach(fn () => removeDir($this->project));

it('ejecuta y lista las tareas de routes/schedule.php', function (): void {
    writeFile($this->project . '/routes/schedule.php', <<<'PHP'
        <?php
        use BootZen\Schedule\Schedule;

        return static function (Schedule $schedule): void {
            $schedule->command('view:clear')->everyMinute();
            $schedule->call(fn () => file_put_contents(storage_path('latido.txt'), 'ok'), 'latido');
            $schedule->command('db:backup')->dailyAt('03:00');
        };
        PHP);

    expect(($this->run)('schedule:run'))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('Vistas compiladas borradas: 0')
        ->toContain('view:clear: listo')
        ->toContain('latido: listo')
        ->not->toContain('db:backup')
        ->and($this->project . '/storage/latido.txt')->toBeFile()
        ->and($this->project . '/storage/cache/schedule.json')->toBeFile();

    expect(($this->run)('schedule:list'))->toBe(0)
        ->and(consoleOutput($this->output))->toMatch('/Cron\s+Próxima\s+Tarea/')
        ->toMatch('/0 3 \* \* \*\s+\d{4}-\d\d-\d\d 03:00\s+db:backup/')
        ->toContain('Horas en America/Mexico_City')
        ->toContain("* * * * * cd '{$this->project}' && '" . PHP_BINARY . "' bin/bootzen schedule:run >> /dev/null 2>&1");
});

it('termina con error si una tarea falla y lo explica si el archivo está mal', function (): void {
    writeFile($this->project . '/routes/schedule.php', "<?php return static function (BootZen\\Schedule\\Schedule \$s): void { \$s->command('mail:test'); };");

    expect(($this->run)('schedule:run'))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('mail:test: falló: El comando mail:test terminó con código 1.');

    writeFile($this->project . '/routes/schedule.php', '<?php return [];');

    expect(($this->run)('schedule:list'))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('routes/schedule.php debe retornar una función');
});

it('sin routes/schedule.php no hace nada', function (): void {
    expect(($this->run)('schedule:list'))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('No hay tareas en routes/schedule.php.')
        ->and(($this->run)('schedule:run'))->toBe(0);
});

it('doctor avisa si hay tareas pero el cron no corre', function (): void {
    writeFile($this->project . '/routes/schedule.php', "<?php return static function (BootZen\\Schedule\\Schedule \$s): void { \$s->command('db:backup')->daily(); };");

    ($this->run)('doctor');
    expect(consoleOutput($this->output))->toContain("! 1 tarea(s) programada(s), pero schedule:run nunca corrió. Agrega al cron: * * * * * cd '{$this->project}' && '" . PHP_BINARY . "' bin/bootzen schedule:run");

    writeFile($this->project . '/storage/cache/schedule.json', json_encode(['last_run' => time() - 3600]));
    ($this->run)('doctor');
    expect(consoleOutput($this->output))->toContain('! schedule:run no corre desde hace 60 minutos.');

    ($this->run)('schedule:run');
    ($this->run)('doctor');
    expect(consoleOutput($this->output))->toContain('✓ 1 tarea(s) programada(s); schedule:run corrió hace 0 min');

    writeFile($this->project . '/routes/schedule.php', "<?php return static function (BootZen\\Schedule\\Schedule \$s): void { \$s->command('x')->dailyAt('99:00'); };");
    ($this->run)('doctor');
    expect(consoleOutput($this->output))->toContain('✗ routes/schedule.php: Hora inválida [99:00]');
});

it('doctor revisa la cola en la base', function (): void {
    writeFile($this->project . '/config/queue.php', "<?php return ['driver' => 'database'];");

    ($this->run)('doctor');
    expect(consoleOutput($this->output))->toContain('✗ Cola: Falta la tabla de la cola.');
});
