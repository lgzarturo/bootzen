<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;
use BootZen\Database\Migrator;
use BootZen\Database\Model;
use BootZen\Middleware\Authenticate;
use BootZen\Middleware\StartSession;
use BootZen\Middleware\VerifyCsrfToken;
use BootZen\Testing\TestClient;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @property int $id
 * @property string $nombre
 */
final class TestClientTestUser extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = ['nombre'];
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-client');
    writeFile($this->project . '/config/app.php', "<?php return ['env' => 'testing'];");
    writeFile($this->project . '/config/session.php', "<?php return ['driver' => 'array'];");
    writeFile($this->project . '/config/auth.php', "<?php return ['model' => TestClientTestUser::class];");
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]];");
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear.php', <<<'PHP'
        <?php
        return new class () extends BootZen\Database\Migration {
            public function up(BootZen\Database\Connection $db): void
            {
                $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, created_at TEXT, updated_at TEXT)');
                $db->exec('CREATE TABLE notas (id INTEGER PRIMARY KEY AUTOINCREMENT, titulo TEXT NOT NULL)');
            }
        };
        PHP);

    $app = new Application($this->project);
    $app->aliasMiddleware('auth', Authenticate::class);
    $app->middlewareGroup('web', [StartSession::class, VerifyCsrfToken::class]);
    $app->routes(static function (Router $router): void {
        $router->group(['middleware' => 'web'], static function (Router $router): void {
            $router->get('/', static fn (): string => '<h1>Hola &amp; adiós</h1><p>Tom &amp; Jerry</p>');
            $router->get('/login', static fn (): string => 'login')->name('login');
            $router->post('/notas', static function (Request $request): Response {
                $datos = $request->validate(['titulo' => 'required|max:20']);
                db()->table('notas')->insert($datos);

                return redirect('/notas/1')->with('status', 'Nota creada.');
            });
            $router->put('/notas/1', static fn (Request $request): array => ['metodo' => 'PUT', 'titulo' => $request->input('titulo')]);
            $router->patch('/notas/1', static fn (): array => ['metodo' => 'PATCH']);
            $router->delete('/notas/1', static fn (): Response => Response::noContent());
            $router->get('/notas/1', static fn (): string => '<p>' . e((string) session('status', 'sin estado')) . '</p>');
            $router->get('/a', static fn (): Response => redirect('/b'));
            $router->get('/b', static fn (): Response => redirect('http://localhost/notas/1'));
            $router->get('/privado', static fn (): string => 'Hola ' . e((string) auth()->user()?->getAttribute('nombre')))->middleware('auth');
            $router->get('/api/notas', static fn (): array => [
                'data' => [['id' => 1, 'titulo' => 'Pan', 'tags' => ['a', 'b']]],
                'total' => 1,
            ]);
            $router->post('/api/notas', static fn (Request $request): Response => Response::json(['ok' => true, 'tipo' => $request->getHeader('content-type')], 201));
            $router->get('/cabecera', static fn (): Response => Response::html('x')->withHeader('X-Prueba', 'si')->withCookie('tema', '', time() - 10));
        });
    });
    $app->make(Migrator::class)->run();

    $this->client = new TestClient($app);
});

afterEach(fn () => removeDir($this->project));

/**
 * Comprueba que la aserción falle con el mensaje indicado.
 */
function expectAssertionFails(Closure $assertion, string $message): void
{
    try {
        $assertion();
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain($message);

        return;
    }

    throw new RuntimeException('La aserción debió fallar: ' . $message);
}

it('hace peticiones y comprueba estado y contenido', function (): void {
    $this->client->get('/')
        ->assertOk()
        ->assertSee('Tom & Jerry')
        ->assertSee('<h1>', false)
        ->assertDontSee('Bye');

    expect($this->client->get('/')->status())->toBe(200);

    $this->client->get('/no-existe')->assertNotFound();
    $this->client->delete('/notas/1')->assertNoContent();
    $this->client->put('/notas/1', ['titulo' => 'x'])->assertJson(['metodo' => 'PUT', 'titulo' => 'x']);
    $this->client->patch('/notas/1')->assertJsonPath('metodo', 'PATCH');
});

it('valida, redirige con errores y guarda en la base de datos', function (): void {
    $this->client->from('/notas/nueva')->post('/notas', ['titulo' => ''])
        ->assertRedirect('/notas/nueva')
        ->assertSessionHasErrors(['titulo'])
        ->assertSessionHasErrors();

    $this->client->assertDatabaseCount('notas', 0);

    $this->client->post('/notas', ['titulo' => 'Pan'])
        ->assertRedirect('/notas/1')
        ->assertSessionHas('status', 'Nota creada.')
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    $this->client->assertDatabaseHas('notas', ['titulo' => 'Pan'])
        ->assertDatabaseMissing('notas', ['titulo' => 'Leche'])
        ->assertDatabaseCount('notas', 1);

    // La cookie de sesión viaja entre peticiones: el flash llega a la siguiente página
    $this->client->get('/notas/1')->assertSee('Nota creada.');
    $this->client->get('/notas/1')->assertSee('sin estado')->assertSessionMissing('status');
});

it('sigue redirecciones cuando se pide', function (): void {
    $this->client->get('/a')->assertRedirect('/b');
    $this->client->followingRedirects()->get('/a')->assertOk()->assertSee('sin estado');
});

it('inicia sesión con actingAs', function (): void {
    $user = TestClientTestUser::create(['nombre' => 'Ana']);

    $this->client->assertGuest();
    $this->client->get('/privado')->assertRedirect('/login');
    $this->client->actingAs($user)->get('/privado')->assertOk()->assertSee('Hola Ana');
    $this->client->assertAuthenticated();

    // Con una sesión ya abierta, actingAs la reutiliza
    $this->client->actingAs($user)->get('/privado')->assertSee('Hola Ana');
});

it('envía y comprueba JSON', function (): void {
    $this->client->getJson('/api/notas')
        ->assertOk()
        ->assertJson(['data' => [['titulo' => 'Pan', 'tags' => ['a', 'b']]], 'total' => 1])
        ->assertJsonPath('data.0.titulo', 'Pan')
        ->assertJsonCount(1, 'data');

    expect($this->client->getJson('/api/notas')->json('data.0.id'))->toBe(1);

    $this->client->postJson('/api/notas', ['x' => 1])->assertCreated()->assertJsonPath('tipo', 'application/json');
    $this->client->withHeaders(['Accept' => 'application/json'])->post('/notas', [])->assertUnprocessable();
    $this->client->putJson('/notas/1', ['titulo' => 'y'])->assertJsonPath('titulo', 'y');
    $this->client->deleteJson('/notas/1')->assertNoContent();
});

it('comprueba cabeceras y borra cookies vencidas', function (): void {
    $this->client->get('/cabecera')->assertHeader('X-Prueba', 'si')->assertHeader('X-Prueba');

    expectAssertionFails(fn () => $this->client->get('/cabecera')->assertHeader('X-Falta'), 'no tiene la cabecera X-Falta');
});

it('explica qué llegó cuando una aserción falla', function (): void {
    expectAssertionFails(fn () => $this->client->get('/no-existe')->assertOk(), 'Se esperaba el estado 200 y llegó 404. Respuesta:');
    expectAssertionFails(fn () => $this->client->get('/a')->assertOk(), 'Redirige a /b.');
    expectAssertionFails(fn () => $this->client->get('/')->assertRedirect(), 'Se esperaba una redirección');
    expectAssertionFails(fn () => $this->client->get('/')->assertSee('Nada'), 'No aparece "Nada"');
    expectAssertionFails(fn () => $this->client->get('/')->assertDontSee('Hola &amp; adiós', false), 'y no debería');
    expectAssertionFails(fn () => $this->client->get('/')->json(), 'La respuesta no es JSON válido');
    expectAssertionFails(fn () => $this->client->getJson('/api/notas')->json('data.5'), 'no tiene la ruta [data.5]');
    expectAssertionFails(fn () => $this->client->getJson('/api/notas')->assertJson(['falta' => 1]), 'no tiene la clave [falta]');
    expectAssertionFails(fn () => $this->client->getJson('/api/notas')->assertJson(['data' => [['titulo' => 'Leche']]]), 'El valor de [data.0.titulo]');
    expectAssertionFails(fn () => $this->client->get('/')->assertSessionHasErrors(), 'No hay errores de validación');
    expectAssertionFails(fn () => $this->client->post('/notas', [])->assertSessionHasErrors(['otro']), 'No hay error de validación para [otro]');
    expectAssertionFails(fn () => $this->client->post('/notas', [])->assertSessionHasNoErrors(), 'Hay errores de validación');
    expectAssertionFails(fn () => $this->client->get('/')->assertSessionHas('nada'), 'no tiene la clave [nada]');
    expectAssertionFails(fn () => $this->client->assertDatabaseHas('notas', ['titulo' => 'X']), 'No hay ninguna fila en notas');
    expectAssertionFails(fn () => $this->client->assertDatabaseCount('notas', 3), 'Se esperaban 3 filas en notas y hay 0');
    expectAssertionFails(fn () => $this->client->assertAuthenticated(), 'Se esperaba un usuario autenticado');
});
