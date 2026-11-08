<?php

declare(strict_types=1);

use BootZen\Auth\Hash;
use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Core\Router;
use BootZen\Database\Migrator;
use BootZen\Database\Model;
use BootZen\Middleware\Authenticate;
use BootZen\Middleware\RememberMe;
use BootZen\Middleware\StartSession;
use BootZen\Middleware\VerifyCsrfToken;
use BootZen\Testing\TestClient;

/**
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string|null $email_verified_at
 */
final class RememberTestUser extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = ['email', 'password', 'email_verified_at'];
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-remember');
    writeFile($this->project . '/config/app.php', "<?php return ['env' => 'testing', 'key' => 'base64:clave-de-prueba'];");
    writeFile($this->project . '/config/session.php', "<?php return ['driver' => 'array'];");
    writeFile($this->project . '/config/auth.php', "<?php return ['model' => RememberTestUser::class, 'remember_days' => 10];");
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]];");
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear.php', <<<'PHP'
        <?php
        return new class () extends BootZen\Database\Migration {
            public function up(BootZen\Database\Connection $db): void
            {
                $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT, password TEXT, email_verified_at TEXT, created_at TEXT, updated_at TEXT)');
                $db->exec('CREATE TABLE remember_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, selector TEXT NOT NULL UNIQUE, validator_hash TEXT NOT NULL, user_id INTEGER NOT NULL, expires_at TEXT NOT NULL, created_at TEXT)');
            }
        };
        PHP);

    $app = new Application($this->project);
    $app->aliasMiddleware('auth', Authenticate::class);
    $app->middlewareGroup('web', [StartSession::class, RememberMe::class, VerifyCsrfToken::class]);
    $app->routes(static function (Router $router): void {
        $router->group(['middleware' => 'web'], static function (Router $router): void {
            $router->get('/login', static fn (): string => 'login')->name('login');
            $router->post('/login', static fn (Request $request): Response => auth()->attempt(['email' => $request->input('email'), 'password' => $request->input('password')], $request->boolean('remember'))
                ? redirect('/panel')
                : redirect('/login'));
            $router->post('/logout', static function (): Response {
                auth()->logout();

                return redirect('/login');
            });
            $router->post('/otros', static fn (Request $request): array => ['ok' => auth()->logoutOtherDevices((string) $request->input('password'))]);
            $router->get('/panel', static fn (): string => 'Hola ' . e((string) auth()->user()?->getAttribute('email')))->middleware('auth');
            $router->get('/verificado', static fn (): string => 'verificado')->middleware(['auth', 'verified']);
            $router->get('/aviso', static fn (): string => 'revisa tu correo')->name('verification.notice');
            $router->get('/factura/{id}', static fn (int $id): string => "factura {$id}")->name('factura')->middleware('signed');
        });
    });
    $app->make(Migrator::class)->run();

    $this->app = $app;
    $this->client = new TestClient($app);
    $this->ana = RememberTestUser::create(['email' => 'ana@example.com', 'password' => Hash::make('secreto-largo')]);
    $this->cookie = 'bootzen_remember';
});

afterEach(fn () => removeDir($this->project));

it('con recordarme vuelve a entrar sin la cookie de sesión y renueva el token', function (): void {
    $this->client->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo', 'remember' => '1'])->assertRedirect('/panel');
    $primera = $this->client->cookies()[$this->cookie] ?? '';

    expect($primera)->toMatch('/^[a-f0-9]{24}:[a-f0-9]{64}$/')
        ->and(db()->table('remember_tokens')->count())->toBe(1)
        ->and(db()->table('remember_tokens')->value('validator_hash'))->not->toContain(explode(':', $primera)[1]);

    $this->client->closeBrowser()->get('/panel')->assertOk()->assertSee('Hola ana@example.com');
    $segunda = $this->client->cookies()[$this->cookie] ?? '';

    expect($segunda)->not->toBe($primera)
        ->and(db()->table('remember_tokens')->count())->toBe(1);

    // Cerrar sesión borra el token y la cookie
    $this->client->post('/logout');
    expect($this->client->cookies())->not->toHaveKey($this->cookie)
        ->and(db()->table('remember_tokens')->count())->toBe(0);
    $this->client->closeBrowser()->get('/panel')->assertRedirect('/login');
});

it('sin recordarme no deja cookie ni token', function (): void {
    $this->client->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo'])->assertRedirect('/panel');

    expect($this->client->cookies())->not->toHaveKey($this->cookie)
        ->and(db()->table('remember_tokens')->count())->toBe(0);
    $this->client->closeBrowser()->get('/panel')->assertRedirect('/login');
});

it('una cookie copiada y ya usada borra todos los tokens del usuario', function (): void {
    $this->client->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo', 'remember' => '1']);
    $robada = $this->client->cookies()[$this->cookie];
    $this->client->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo', 'remember' => '1']);
    expect(db()->table('remember_tokens')->count())->toBe(2);

    // El dueño la usa (se renueva) y después el ladrón llega con la copia vieja
    $this->client->closeBrowser()->get('/panel')->assertOk();
    [$selector] = explode(':', $robada);
    $falsa = $selector . ':' . str_repeat('0', 64);

    $respuesta = $this->app->run(new Request('GET', '/panel', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], [$this->cookie => $falsa]));

    expect($respuesta->getStatusCode())->toBe(302)
        ->and(db()->table('remember_tokens')->count())->toBe(0)
        ->and($respuesta->getCookies()[$this->cookie]['expires'] ?? null)->toBeLessThan(time());
});

it('un token vencido o mal formado no inicia sesión', function (): void {
    $this->client->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo', 'remember' => '1']);
    db()->table('remember_tokens')->where('id', '>', 0)->update(['expires_at' => '2000-01-01 00:00:00']);

    $this->client->closeBrowser()->get('/panel')->assertRedirect('/login');
    expect(db()->table('remember_tokens')->count())->toBe(0);

    $respuesta = $this->app->run(new Request('GET', '/panel', [], [], [], [], [$this->cookie => 'basura']));
    expect($respuesta->getStatusCode())->toBe(302);
});

it('cambiar la contraseña cierra las demás sesiones y logoutOtherDevices conserva la actual', function (): void {
    $otro = new TestClient($this->app);
    $otro->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo', 'remember' => '1']);
    $this->client->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo']);

    $this->client->post('/otros', ['password' => 'mala'])->assertJson(['ok' => false]);
    $this->client->post('/otros', ['password' => 'secreto-largo'])->assertJson(['ok' => true]);

    $this->client->get('/panel')->assertOk();
    $otro->get('/panel')->assertRedirect('/login');
    $otro->closeBrowser()->get('/panel')->assertRedirect('/login');
    expect(db()->table('remember_tokens')->count())->toBe(0);
});

it('verified manda al aviso a quien no verificó y deja pasar al verificado', function (): void {
    $this->client->actingAs($this->ana)->get('/verificado')->assertRedirect('/aviso');
    $this->client->getJson('/verificado')->assertForbidden()->assertJson(['message' => 'Verifica tu correo para continuar.']);

    $this->ana->update(['email_verified_at' => date('Y-m-d H:i:s')]);
    $this->client->get('/verificado')->assertOk()->assertSee('verificado');
});

it('signed_route firma rutas con nombre y rechaza cambios y vencidos', function (): void {
    $url = signed_route('factura', ['id' => 7], 10);
    $path = (string) parse_url($url, PHP_URL_PATH) . '?' . (string) parse_url($url, PHP_URL_QUERY);

    expect($url)->toStartWith('http://localhost/factura/7?expires=');
    $this->client->get($path)->assertOk()->assertSee('factura 7');
    $this->client->get(str_replace('/factura/7', '/factura/8', $path))->assertForbidden();
    $this->client->get($path . '&extra=1')->assertForbidden();
    $this->client->get('/factura/7')->assertForbidden();

    $firmador = $this->app->make(BootZen\Security\SignedUrl::class);
    $pasada = $firmador->sign('factura', ['id' => 7], time() - 1);
    $this->client->get('/factura/7?' . http_build_query(['expires' => $pasada['expires'], 'signature' => $pasada['signature']]))->assertForbidden();
});

it('forgetRememberedDevices borra los recordarme del usuario', function (): void {
    $this->client->post('/login', ['email' => 'ana@example.com', 'password' => 'secreto-largo', 'remember' => '1']);
    expect(db()->table('remember_tokens')->count())->toBe(1);

    auth()->forgetRememberedDevices($this->ana);

    expect(db()->table('remember_tokens')->count())->toBe(0);
    $this->client->closeBrowser()->get('/panel')->assertRedirect('/login');
});
