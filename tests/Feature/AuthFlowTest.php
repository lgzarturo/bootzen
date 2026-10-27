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
use BootZen\Middleware\RedirectIfAuthenticated;
use BootZen\Middleware\StartSession;
use BootZen\Middleware\VerifyCsrfToken;
use BootZen\Session\SessionStore;
use BootZen\Validation\ValidationException;

/**
 * @property int $id
 * @property string $nombre
 * @property string $email
 * @property string $password
 */
final class AuthFlowTestUser extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = ['nombre', 'email', 'password'];

    protected static array $hidden = ['password'];
}

final class AuthFlowTestController
{
    public function registerForm(): Response
    {
        return view('registro');
    }

    public function register(Request $request): Response
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:60',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = AuthFlowTestUser::create(['password' => Hash::make((string) $datos['password'])] + $datos);
        auth()->login($user);

        return redirect('/panel')->with('status', 'Bienvenida, ' . $user->nombre);
    }

    public function loginForm(): Response
    {
        return view('login');
    }

    public function login(Request $request): Response
    {
        $credenciales = $request->validate(['email' => 'required|email', 'password' => 'required']);

        if (! auth()->attempt($credenciales)) {
            throw ValidationException::withMessages(['email' => 'Las credenciales no coinciden.']);
        }

        return redirect(auth()->intended('/panel'));
    }

    public function logout(): Response
    {
        auth()->logout();

        return redirect('/');
    }

    public function panel(): Response
    {
        return view('panel');
    }

    public function me(): array
    {
        return ['email' => auth()->user()?->getAttribute('email')];
    }
}

/**
 * Cliente mínimo que conserva la cookie de sesión entre peticiones.
 */
final class AuthFlowTestClient
{
    private ?string $cookie = null;

    public function __construct(private readonly Application $app)
    {
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function send(string $method, string $uri, array $body = [], array $headers = [], bool $withToken = true): Response
    {
        if ($withToken && $method !== 'GET') {
            $body['_token'] ??= $this->token();
        }

        $cookies = $this->cookie === null ? [] : ['bootzen_session' => $this->cookie];
        $response = $this->app->run(new Request($method, $uri, $headers, $body, [], ['REMOTE_ADDR' => '203.0.113.7'], $cookies));
        $cookie = $response->getCookies()['bootzen_session']['value'] ?? null;

        if (is_string($cookie)) {
            $this->cookie = $cookie;
        }

        return $response;
    }

    public function cookie(): ?string
    {
        return $this->cookie;
    }

    public function forgetCookie(): void
    {
        $this->cookie = null;
    }

    /**
     * Token CSRF de la sesión actual (como lo vería el formulario).
     */
    public function token(): string
    {
        $store = $this->app->make(SessionStore::class);
        $data = $this->cookie === null || ! $store instanceof SessionStore ? null : $store->read($this->cookie);

        return is_string($data['_token'] ?? null) ? $data['_token'] : '';
    }
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-auth');

    writeFile($this->project . '/config/app.php', "<?php return ['env' => 'local'];");
    writeFile($this->project . '/config/session.php', "<?php return ['driver' => 'array'];");
    writeFile($this->project . '/config/cache.php', "<?php return ['driver' => 'array'];");
    writeFile($this->project . '/config/mail.php', "<?php return ['driver' => 'array'];");
    writeFile($this->project . '/config/auth.php', "<?php return ['model' => AuthFlowTestUser::class, 'home' => '/panel'];");
    writeFile($this->project . '/config/database.php', "<?php return ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:']]];");
    writeFile($this->project . '/database/migrations/2026_01_01_000000_crear_users.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Migration;

        return new class () extends Migration {
            public function up(Connection $db): void
            {
                $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL, created_at TEXT, updated_at TEXT)');
            }
        };
        PHP);
    $form = <<<'PHP'
        <form method="POST">@csrf
        <input name="nombre" value="{{ old('nombre') }}"> @error('nombre')<p>{{ $message }}</p>@enderror
        <input name="email" value="{{ old('email') }}"> @error('email')<p>{{ $message }}</p>@enderror
        <input name="password" value="{{ old('password') }}"> @error('password')<p>{{ $message }}</p>@enderror
        </form>
        PHP;
    writeFile($this->project . '/resources/views/registro.php', $form);
    writeFile($this->project . '/resources/views/login.php', $form);
    writeFile($this->project . '/resources/views/panel.php', "@if (session('status'))<p>{{ session('status') }}</p>@endif<h1>Hola {{ auth()->user()?->getAttribute('nombre') }}</h1>");

    $this->app = new Application($this->project);
    $this->app->aliasMiddleware('auth', Authenticate::class);
    $this->app->aliasMiddleware('guest', RedirectIfAuthenticated::class);
    $this->app->middlewareGroup('web', [StartSession::class, VerifyCsrfToken::class]);
    $this->app->routes(static function (Router $router): void {
        $router->group(['middleware' => 'web'], static function (Router $router): void {
            $router->get('/', static fn (): string => 'inicio');
            $router->get('/up', static fn (): array => ['status' => 'ok']);
            $router->group(['middleware' => 'guest'], static function (Router $router): void {
                $router->get('/registro', [AuthFlowTestController::class, 'registerForm']);
                $router->post('/registro', [AuthFlowTestController::class, 'register']);
                $router->get('/login', [AuthFlowTestController::class, 'loginForm'])->name('login');
                $router->post('/login', [AuthFlowTestController::class, 'login']);
            });
            $router->group(['middleware' => 'auth'], static function (Router $router): void {
                $router->get('/panel', [AuthFlowTestController::class, 'panel']);
                $router->post('/logout', [AuthFlowTestController::class, 'logout']);
                $router->get('/api/yo', [AuthFlowTestController::class, 'me']);
            });
        });
    });
    $this->app->make(Migrator::class)->run();
    $this->client = new AuthFlowTestClient($this->app);

    $this->register = function (): Response {
        $this->client->send('GET', '/registro');

        return $this->client->send('POST', '/registro', [
            'nombre' => 'Ana',
            'email' => 'ana@ejemplo.com',
            'password' => 'secreto-largo',
            'password_confirmation' => 'secreto-largo',
        ]);
    };
});

afterEach(fn () => removeDir($this->project));

it('vuelve al formulario con errores y el input anterior, sin la contraseña', function (): void {
    expect($this->client->send('GET', '/registro')->getBody())->toContain('name="_token" value="' . $this->client->token() . '"');

    $response = $this->client->send('POST', '/registro', ['nombre' => 'Ana', 'email' => 'no-es-correo', 'password' => 'corta']);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('location'))->toBe('/registro');

    $form = $this->client->send('GET', '/registro')->getBody();

    expect($form)->toContain('value="Ana"')
        ->and($form)->toContain('<p>El campo email debe ser un correo electrónico válido.</p>')
        ->and($form)->toContain('<p>El campo password debe tener al menos 8 caracteres.</p>')
        ->and($form)->not->toContain('corta');

    // Los errores son flash: desaparecen en la siguiente visita
    expect($this->client->send('GET', '/registro')->getBody())->not->toContain('<p>');
});

it('registra, inicia sesión y muestra el mensaje flash una sola vez', function (): void {
    $response = ($this->register)();

    expect($response->getHeader('location'))->toBe('/panel')
        ->and(Hash::check('secreto-largo', AuthFlowTestUser::where('email', 'ana@ejemplo.com')->firstOrFail()->password))->toBeTrue();

    $panel = $this->client->send('GET', '/panel')->getBody();

    expect($panel)->toContain('<p>Bienvenida, Ana</p>')
        ->and($panel)->toContain('<h1>Hola Ana</h1>')
        ->and($this->client->send('GET', '/panel')->getBody())->toBe('<h1>Hola Ana</h1>');
});

it('cambia el id de sesión al iniciar y al cerrar sesión', function (): void {
    $this->client->send('GET', '/registro');
    $invitado = $this->client->cookie();
    ($this->register)();
    $autenticado = $this->client->cookie();

    expect($autenticado)->not->toBe($invitado);

    $salida = $this->client->send('POST', '/logout');

    expect($salida->getHeader('location'))->toBe('/')
        ->and($this->client->cookie())->not->toBe($autenticado)
        ->and($this->client->send('GET', '/panel')->getHeader('location'))->toBe('/login');
});

it('manda al login a los invitados y los regresa a donde iban', function (): void {
    ($this->register)();
    $this->client->send('POST', '/logout');

    expect($this->client->send('GET', '/panel?tab=2')->getHeader('location'))->toBe('/login');

    $this->client->send('GET', '/login');
    $fallo = $this->client->send('POST', '/login', ['email' => 'ana@ejemplo.com', 'password' => 'incorrecta']);
    expect($fallo->getHeader('location'))->toBe('/login')
        ->and($this->client->send('GET', '/login')->getBody())->toContain('Las credenciales no coinciden.');

    $ok = $this->client->send('POST', '/login', ['email' => 'ana@ejemplo.com', 'password' => 'secreto-largo']);

    expect($ok->getHeader('location'))->toBe('/panel?tab=2')
        ->and($this->client->send('GET', '/login')->getHeader('location'))->toBe('/panel');
});

it('rechaza formularios sin token CSRF con 419', function (): void {
    $this->client->send('GET', '/registro');
    $response = $this->client->send('POST', '/registro', ['nombre' => 'Ana'], [], false);

    expect($response->getStatusCode())->toBe(419)
        ->and($response->getBody())->toContain('La sesión expiró');
});

it('responde JSON 422 y 401 a clientes que piden JSON', function (): void {
    $json = ['Accept' => 'application/json'];
    $this->client->send('GET', '/registro');
    $invalido = $this->client->send('POST', '/registro', ['email' => 'x'], $json);
    $payload = json_decode($invalido->getBody(), true);

    expect($invalido->getStatusCode())->toBe(422)
        ->and($payload['errors']['email'])->toBe(['El campo email debe ser un correo electrónico válido.'])
        ->and($payload['errors'])->toHaveKey('nombre');

    $this->client->forgetCookie();
    expect($this->client->send('GET', '/api/yo', [], $json)->getStatusCode())->toBe(401);

    ($this->register)();
    expect($this->client->send('GET', '/api/yo', [], $json)->getBody())->toBe('{"email":"ana@ejemplo.com"}');
});

it('no crea sesión ni cookie si la petición no la usa', function (): void {
    $response = $this->client->send('GET', '/up');

    expect($response->getCookies())->toBe([])
        ->and($this->client->cookie())->toBeNull();
});

it('ignora ids de sesión desconocidos', function (): void {
    $response = $this->app->run(new Request('GET', '/registro', [], [], [], [], ['bootzen_session' => str_repeat('a', 40)]));
    $cookie = $response->getCookies()['bootzen_session'];

    expect($cookie['value'])->not->toBe(str_repeat('a', 40))
        ->and($cookie['httponly'])->toBeTrue()
        ->and($cookie['samesite'])->toBe('Lax')
        ->and($cookie['secure'])->toBeFalse()
        ->and($cookie['expires'])->toBeGreaterThan(time() + 7000);
});
