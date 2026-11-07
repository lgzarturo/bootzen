<?php

declare(strict_types=1);

use BootZen\Console\Console;
use BootZen\Core\Application;
use BootZen\Database\Migrator;
use BootZen\Frontend\Tailwind;
use BootZen\Mail\ArrayMailer;
use BootZen\Mail\Mailer;
use BootZen\Mail\MailService;
use BootZen\Mail\Message;
use BootZen\Queue\DatabaseQueue;
use BootZen\Queue\Job;
use BootZen\Queue\SyncQueue;

final class QueueFeatureAvisar extends Job
{
    public function __construct(public readonly string $email)
    {
    }

    public function handle(Mailer $mailer): void
    {
        $mailer->send(new Message($this->email, 'Aviso', 'Tu pedido salió'));
    }
}

final class QueueFeatureFalla extends Job
{
    public function __construct(public readonly int $pedido)
    {
    }

    public function handle(): void
    {
        throw new RuntimeException("El pedido {$this->pedido} no existe");
    }
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-queue-app');
    $this->boot = function (string $driver): Application {
        writeFile($this->project . '/config/queue.php', "<?php return ['driver' => '{$driver}'];");
        writeFile($this->project . '/config/mail.php', "<?php return ['driver' => 'array'];");
        writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => 'database/app.sqlite']]];");

        return new Application($this->project);
    };
    $this->sent = function (Application $app): array {
        $mailer = $app->make(Mailer::class);

        return $mailer instanceof ArrayMailer ? $mailer->sent() : [];
    };
    $this->output = fopen('php://memory', 'w+');
    $this->console = fn (Application $app): Console => new Console($app, new Tailwind($this->project, 'Linux', 'x86_64'), $this->output);
});

afterEach(fn () => removeDir($this->project));

it('con sync, dispatch() y mailer()->queue() se ejecutan en el momento', function (): void {
    $app = ($this->boot)('sync');

    expect(queue())->toBeInstanceOf(SyncQueue::class)
        ->and(mailer())->toBeInstanceOf(MailService::class)
        ->and(dispatch(new QueueFeatureAvisar('ana@ejemplo.com')))->toBe(0);

    mailer()->queue((new Message('luis@ejemplo.com', 'Factura', 'Adjunta'))->cc('eva@ejemplo.com')->attachData('%PDF', 'F-1.pdf', 'application/pdf'));
    $sent = ($this->sent)($app);

    expect($sent)->toHaveCount(2)
        ->and($sent[0]->subject)->toBe('Aviso')
        ->and($sent[1]->to)->toBe('luis@ejemplo.com')
        ->and($sent[1]->getCc())->toBe(['eva@ejemplo.com'])
        ->and($sent[1]->getAttachments()[0]->contents())->toBe('%PDF')
        ->and(($this->console)($app)->run(['bootzen', 'queue:work']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('Con QUEUE_DRIVER=sync los jobs se ejecutan al hacer dispatch()');
});

it('con database, el worker envía lo encolado y los comandos administran los fallidos', function (): void {
    $app = ($this->boot)('database');
    $console = ($this->console)($app);

    expect($console->run(['bootzen', 'queue:table']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('_crear_tablas_de_cola.php')
        ->toContain('QUEUE_DRIVER=database');

    $app->make(Migrator::class)->run();

    expect(dispatch(new QueueFeatureAvisar('ana@ejemplo.com')))->toBe(1)
        ->and(mailer()->queue(new Message('luis@ejemplo.com', 'Hola', 'x'), 0))->toBe(2)
        ->and(dispatch(new QueueFeatureFalla(99)))->toBe(3)
        ->and(($this->sent)($app))->toBe([]);

    expect($console->run(['bootzen', 'queue:work', '--stop-when-empty']))->toBe(0)
        ->and(($this->sent)($app))->toHaveCount(2)
        ->and(consoleOutput($this->output))->toContain('QueueFeatureAvisar #1 listo')
        ->toContain('SendQueuedMail #2 listo')
        ->toContain('QueueFeatureFalla #3 falló (intento 1 de 3), se reintenta en 60 s: El pedido 99 no existe');

    // Agota los intentos sin esperar: lo pasa a fallidos a mano
    $queue = $app->make(DatabaseQueue::class);
    $app->getContainer()->make(BootZen\Database\Connection::class)->statement('UPDATE jobs SET available_at = 0, attempts = 3');
    $console->run(['bootzen', 'queue:work', '--stop-when-empty']);

    expect($queue->failed())->toHaveCount(1)
        ->and($console->run(['bootzen', 'queue:failed']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('#1  ')
        ->toContain('QueueFeatureFalla  (4 intento(s), cola default)')
        ->toContain('RuntimeException: El pedido 99 no existe')
        ->and($console->run(['bootzen', 'queue:retry', '7']))->toBe(1)
        ->and($console->run(['bootzen', 'queue:retry', 'all']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('Vueltos a la cola: 1.')
        ->and($console->run(['bootzen', 'queue:retry', 'x']))->toBe(1)
        ->and(consoleOutput($this->output))->toContain('Indica el id del job fallido o all');

    $app->getContainer()->make(BootZen\Database\Connection::class)->statement('UPDATE jobs SET attempts = 3');
    $console->run(['bootzen', 'queue:work', '--stop-when-empty', '--max-jobs=1']);

    expect($console->run(['bootzen', 'queue:flush']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('Jobs fallidos borrados: 1')
        ->and($console->run(['bootzen', 'queue:failed']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('No hay jobs fallidos.')
        ->and($console->run(['bootzen', 'queue:work', '--stop-when-empty']))->toBe(0)
        ->and(consoleOutput($this->output))->toContain('No hay jobs pendientes.');
});

it('rechaza un driver de cola desconocido', function (): void {
    ($this->boot)('redis');

    expect(fn () => queue())->toThrow(LogicException::class, 'Driver de cola no soportado. Usa sync o database');
});
