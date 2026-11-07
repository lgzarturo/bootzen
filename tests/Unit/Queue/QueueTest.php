<?php

declare(strict_types=1);

use BootZen\Console\Generator;
use BootZen\Core\Container;
use BootZen\Database\Connection;
use BootZen\Database\Migrator;
use BootZen\Queue\DatabaseQueue;
use BootZen\Queue\Job;
use BootZen\Queue\SyncQueue;
use BootZen\Queue\Worker;

/**
 * Registro de lo que hicieron los jobs de prueba.
 */
final class QueueTestLog
{
    /**
     * @var list<string>
     */
    public static array $entries = [];
}

final class QueueTestGreeting
{
    public function hello(string $name): string
    {
        return "hola {$name}";
    }
}

final class QueueTestSaludar extends Job
{
    /**
     * @param list<string> $etiquetas
     */
    public function __construct(public readonly string $nombre, public readonly array $etiquetas = [], public readonly ?int $id = null)
    {
    }

    public function handle(QueueTestGreeting $greeting): void
    {
        QueueTestLog::$entries[] = $greeting->hello($this->nombre) . ' ' . implode(',', $this->etiquetas);
    }
}

final class QueueTestFalla extends Job
{
    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public readonly int $id)
    {
    }

    public function handle(): void
    {
        throw new RuntimeException("no se pudo con {$this->id}");
    }

    public function failed(Throwable $exception): void
    {
        QueueTestLog::$entries[] = 'failed: ' . $exception->getMessage();
    }
}

final class QueueTestPrivado extends Job
{
    public function __construct(private readonly int $id)
    {
    }

    public function handle(): void
    {
        QueueTestLog::$entries[] = (string) $this->id;
    }
}

final class QueueTestConObjeto extends Job
{
    public function __construct(public readonly object $modelo)
    {
    }

    public function handle(): void
    {
    }
}

beforeEach(function (): void {
    QueueTestLog::$entries = [];
    $this->dir = makeTempDir('bootzen-queue');
    $this->db = Connection::fromConfig(['driver' => 'sqlite', 'database' => ':memory:'], $this->dir);

    (new Generator($this->dir))->queueTable();
    (new Migrator($this->db, $this->dir . '/database/migrations'))->run();

    $this->now = 1_000_000;
    $this->queue = new DatabaseQueue($this->db, 300, clock: fn (): int => $this->now);
    $this->output = [];
    $this->worker = new Worker($this->queue, new Container(), function (string $line): void {
        $this->output[] = $line;
    });
});

afterEach(fn () => removeDir($this->dir));

it('guarda los parámetros del constructor y reconstruye el job', function (): void {
    $job = new QueueTestSaludar('Ana', ['vip', 'nueva'], 7);

    expect($job->toPayload())->toBe(['nombre' => 'Ana', 'etiquetas' => ['vip', 'nueva'], 'id' => 7])
        ->and(Job::fromPayload(QueueTestSaludar::class, ['nombre' => 'Ana']))->toEqual(new QueueTestSaludar('Ana'))
        ->and(fn () => (new QueueTestPrivado(1))->toPayload())->toThrow(InvalidArgumentException::class, 'debe ser una propiedad pública')
        ->and(fn () => (new QueueTestConObjeto(new stdClass()))->toPayload())->toThrow(InvalidArgumentException::class, 'usa valores simples (por ejemplo el id, no el modelo)')
        ->and(fn () => Job::fromPayload(stdClass::class, []))->toThrow(InvalidArgumentException::class, 'no es un Job');
});

it('ejecuta en el momento con sync, con dependencias del contenedor', function (): void {
    expect((new SyncQueue(new Container()))->push(new QueueTestSaludar('Luis', ['a'])))->toBe(0)
        ->and(QueueTestLog::$entries)->toBe(['hola Luis a'])
        ->and(fn () => (new SyncQueue(new Container()))->push(new QueueTestConObjeto(new stdClass())))->toThrow(InvalidArgumentException::class);
});

it('encola, respeta el retraso y procesa en orden', function (): void {
    $this->queue->push(new QueueTestSaludar('Ana'));
    $this->queue->push(new QueueTestSaludar('Luis'), 60);
    $this->queue->push(new QueueTestSaludar('Eva'));

    expect($this->queue->size())->toBe(3)
        ->and($this->worker->run(stopWhenEmpty: true))->toBe(2)
        ->and(QueueTestLog::$entries)->toBe(['hola Ana ', 'hola Eva '])
        ->and($this->output[0])->toMatch('/^\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\] QueueTestSaludar #1 listo \(\d+\.\d\d s\)$/');

    $this->now += 60;

    expect($this->worker->runNext())->toBeTrue()
        ->and(QueueTestLog::$entries[2])->toBe('hola Luis ')
        ->and($this->queue->size())->toBe(0)
        ->and($this->worker->runNext())->toBeFalse();
});

it('reintenta con espera y al agotar los intentos lo pasa a fallidos', function (): void {
    $this->queue->push(new QueueTestFalla(12));

    $this->worker->runNext();

    expect($this->output[0])->toContain('QueueTestFalla #1 falló (intento 1 de 2), se reintenta en 30 s: no se pudo con 12')
        ->and($this->worker->runNext())->toBeFalse();

    $this->now += 30;
    $this->worker->runNext();
    $failed = $this->queue->failed();

    expect($this->output[1])->toContain('falló definitivamente: no se pudo con 12')
        ->and(QueueTestLog::$entries)->toBe(['failed: no se pudo con 12'])
        ->and($this->queue->size())->toBe(0)
        ->and($failed)->toHaveCount(1)
        ->and($failed[0])->toMatchArray(['class' => QueueTestFalla::class, 'attempts' => 2, 'error' => 'RuntimeException: no se pudo con 12', 'failed_at' => $this->now]);

    expect($this->queue->retry())->toBe(1)
        ->and($this->queue->failed())->toBe([])
        ->and($this->queue->size())->toBe(1);

    // Los intentos empiezan de cero tras queue:retry
    $this->worker->runNext();
    expect($this->output[2])->toContain('(intento 1 de 2)');
});

it('manda a fallidos un job cuya clase ya no existe', function (): void {
    $this->db->insert("INSERT INTO jobs (queue, class, payload, attempts, available_at, created_at) VALUES ('default', 'App\\Jobs\\Borrado', '{}', 0, 0, 0)");

    $this->worker->runNext();

    expect($this->queue->failed()[0]['error'])->toContain('La clase App\Jobs\Borrado no existe o no es un Job')
        ->and($this->queue->retry(999))->toBe(0)
        ->and($this->queue->flushFailed())->toBe(1);
});

it('no entrega el mismo job a dos workers y recupera los abandonados', function (): void {
    $this->queue->push(new QueueTestSaludar('Ana'));
    $this->queue->push(new QueueTestSaludar('Luis'));
    $otro = new DatabaseQueue($this->db, 300, clock: fn (): int => $this->now);

    $first = $this->queue->pop();
    $second = $otro->pop();

    expect($first?->id)->toBe(1)
        ->and($second?->id)->toBe(2)
        ->and($otro->pop())->toBeNull();

    // El worker que tomó el 1 murió: tras retry_after vuelve a estar disponible, con un intento más
    $this->now += 300;
    $again = $otro->pop();

    expect($again?->id)->toBe(1)->and($again?->attempts)->toBe(2);
});

it('se detiene por cantidad o por tiempo y espera si la cola está vacía', function (): void {
    foreach (['a', 'b', 'c'] as $name) {
        $this->queue->push(new QueueTestSaludar($name));
    }

    expect($this->worker->run(maxJobs: 2))->toBe(2);

    $seconds = 0.0;
    $sleeps = [];
    $worker = new Worker(
        $this->queue,
        new Container(),
        static function (string $line): void {
        },
        clock: function () use (&$seconds): float {
            return $seconds;
        },
        sleep: function (int $wait) use (&$seconds, &$sleeps): void {
            $sleeps[] = $wait;
            $seconds += $wait;
        }
    );

    expect($worker->run(maxTime: 10, sleep: 4))->toBe(1)
        ->and($sleeps)->toBe([4, 4, 4]);
});

it('explica cómo crear la tabla si falta', function (): void {
    $db = Connection::fromConfig(['driver' => 'sqlite', 'database' => ':memory:'], $this->dir);

    expect(fn () => (new DatabaseQueue($db))->push(new QueueTestSaludar('Ana')))
        ->toThrow(RuntimeException::class, 'Falta la tabla de la cola. Créala con php bin/bootzen queue:table y php bin/bootzen migrate.')
        ->and(fn () => new DatabaseQueue($db, table: 'jobs; DROP TABLE x'))->toThrow(InvalidArgumentException::class);
});

it('genera la migración de la cola una sola vez y jobs con make:job', function (): void {
    $generator = new Generator($this->dir);
    $files = $generator->job('enviar_factura');

    expect($files)->toBe(['app/Jobs/EnviarFactura.php'])
        ->and((string) file_get_contents($this->dir . '/app/Jobs/EnviarFactura.php'))->toContain('final class EnviarFactura extends Job')
        ->and(fn () => $generator->queueTable())->toThrow(RuntimeException::class, 'Ya existe la migración de la cola');

    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($this->dir . '/app/Jobs/EnviarFactura.php'), $output, $status);
    expect($status)->toBe(0);
});
