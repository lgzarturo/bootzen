<?php

declare(strict_types=1);

use BootZen\Auth\Gate;
use BootZen\Console\Console;
use BootZen\Core\Application;
use BootZen\Core\HttpException;
use BootZen\Core\Router;
use BootZen\Database\Migrator;
use BootZen\Frontend\Tailwind;
use BootZen\Middleware\Authenticate;
use BootZen\Middleware\StartSession;
use BootZen\Testing\TestClient;

require_once dirname(__DIR__) . '/Fixtures/policies.php';

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-gate');
    writeFile($this->project . '/config/app.php', "<?php return ['env' => 'testing'];");
    writeFile($this->project . '/config/session.php', "<?php return ['driver' => 'array'];");
    writeFile($this->project . '/config/auth.php', "<?php return ['model' => GateUser::class, 'roles' => ['admin' => ['*'], 'editor' => ['notas.*', 'reportes.ver'], 'usuario' => []]];");
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => 'database/app.sqlite']]];");
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear.php', <<<'PHP'
        <?php
        return new class () extends BootZen\Database\Migration {
            public function up(BootZen\Database\Connection $db): void
            {
                $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, role TEXT NOT NULL DEFAULT 'usuario', created_at TEXT, updated_at TEXT)");
                $db->exec('CREATE TABLE notas (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, titulo TEXT, created_at TEXT, updated_at TEXT)');
            }
        };
        PHP);

    $app = new Application($this->project);
    $app->aliasMiddleware('auth', Authenticate::class);
    $app->middlewareGroup('web', [StartSession::class]);
    $app->routes(static function (Router $router): void {
        $router->group(['middleware' => 'web'], static function (Router $router): void {
            $router->get('/login', static fn (): string => 'login')->name('login');
            $router->get('/admin', static fn (): string => 'panel')->middleware(['auth', 'role:admin']);
            $router->get('/edicion', static fn (): string => 'edición')->middleware(['auth', 'role:editor,admin']);
            $router->get('/reportes', static fn (): string => 'reportes')->middleware(['auth', 'can:reportes.ver']);
            $router->get('/notas/{id}', static function (int $id): string {
                authorize('update', GateNota::findOrFail($id), 404);

                return 'nota ' . $id;
            });
        });
    });
    $app->make(Migrator::class)->run();

    $this->app = $app;
    $this->gate = $app->make(Gate::class);
    $this->user = static function (string $role): GateUser {
        // role no es fillable: se asigna aparte, como haría user:role
        $user = GateUser::create(['email' => "{$role}@example.com"]);
        $user->setAttribute('role', $role);
        $user->save();

        return $user;
    };
});

afterEach(fn () => removeDir($this->project));

it('decide por permisos del rol con comodín y el admin pasa todo', function (): void {
    $admin = ($this->user)('admin');
    $editor = ($this->user)('editor');
    $usuario = ($this->user)('usuario');
    $raro = ($this->user)('fantasma');

    expect($this->gate->allows($editor, 'notas.borrar'))->toBeTrue()
        ->and($this->gate->allows($editor, 'reportes.ver'))->toBeTrue()
        ->and($this->gate->allows($editor, 'reportes.borrar'))->toBeFalse()
        ->and($this->gate->allows($editor, 'notasx.borrar'))->toBeFalse()
        ->and($this->gate->allows($usuario, 'notas.ver'))->toBeFalse()
        ->and($this->gate->allows($raro, 'notas.ver'))->toBeFalse()
        ->and($this->gate->allows(null, 'notas.ver'))->toBeFalse()
        ->and($this->gate->allows($admin, 'lo-que-sea'))->toBeTrue()
        ->and($this->gate->roles())->toBe(['admin', 'editor', 'usuario'])
        ->and($this->gate->hasRole($editor, 'admin', 'editor'))->toBeTrue()
        ->and($editor->hasRole('admin'))->toBeFalse()
        ->and($editor->can('notas.crear'))->toBeTrue();
});

it('usa la política del modelo por convención y el admin la salta', function (): void {
    $ana = ($this->user)('usuario');
    $beto = ($this->user)('editor');
    $admin = ($this->user)('admin');
    $invitado = ($this->user)('invitado');
    $nota = GateNota::create(['user_id' => $ana->id, 'titulo' => 'Mía']);

    expect($ana->can('update', $nota))->toBeTrue()
        // La política decide aunque el rol de Beto tenga notas.*
        ->and($beto->can('update', $nota))->toBeFalse()
        ->and($admin->can('update', $nota))->toBeTrue()
        // Con la clase (sin registro) se llama sin el modelo
        ->and($ana->can('create', GateNota::class))->toBeTrue()
        ->and($invitado->can('create', GateNota::class))->toBeFalse()
        // Método que la política no tiene: deciden los permisos del rol
        ->and($beto->can('notas.exportar', $nota))->toBeTrue()
        ->and($ana->can('delete', $nota))->toBeFalse()
        // Modelo sin política: permisos del rol
        ->and($beto->can('notas.ver', GateSinPolitica::class))->toBeTrue()
        ->and(fn () => $this->gate->authorize($beto, 'update', $nota))->toThrow(HttpException::class, 'No tienes permiso para hacer esto.');

    try {
        $this->gate->authorize($beto, 'update', $nota, 404);
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
});

it('protege rutas con role, can y authorize', function (): void {
    $client = new TestClient($this->app);
    $ana = ($this->user)('usuario');
    $editor = ($this->user)('editor');
    $admin = ($this->user)('admin');
    $nota = GateNota::create(['user_id' => $ana->id, 'titulo' => 'Mía']);

    $client->get('/admin')->assertRedirect('/login');
    $client->actingAs($ana)->get('/admin')->assertForbidden();
    $client->get('/reportes')->assertForbidden();
    $client->get('/notas/' . $nota->id)->assertOk()->assertSee('nota ' . $nota->id);

    $client->actingAs($editor)->get('/edicion')->assertOk();
    $client->get('/reportes')->assertOk();
    $client->get('/notas/' . $nota->id)->assertNotFound();

    // El admin entra a todo, también a rutas de otros roles
    $client->actingAs($admin)->get('/admin')->assertOk();
    $client->get('/edicion')->assertOk();
    $client->get('/notas/' . $nota->id)->assertOk();
});

it('cambia roles desde la terminal con user:role', function (): void {
    ($this->user)('usuario');
    $output = fopen('php://memory', 'w+');
    $run = fn (string ...$argv): int => (new Console($this->app, new Tailwind($this->project, 'Linux', 'x86_64'), $output, static fn (): int => 0))->run(['bootzen', ...$argv]);

    expect($run('user:role', 'USUARIO@example.com', 'admin'))->toBe(0)
        ->and(GateUser::where('email', 'usuario@example.com')->firstOrFail()->role)->toBe('admin')
        ->and($run('user:role', 'usuario@example.com', 'jefe'))->toBe(1)
        ->and($run('user:role', 'nadie@example.com', 'admin'))->toBe(1)
        ->and(consoleOutput($output))->toContain('usuario@example.com ahora tiene el rol admin.')
        ->toContain('El rol [jefe] no está en config/auth.php (roles). Roles: admin, editor, usuario.')
        ->toContain('No hay un usuario con el correo nadie@example.com.');
});
