<?php

declare(strict_types=1);

use App\Models\User;
use BootZen\Auth\Hash;
use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Mail\ArrayMailer;
use BootZen\Mail\Mailer;
use BootZen\Mail\Message;

/**
 * Navegador mínimo: envía peticiones a la misma aplicación y conserva la cookie de sesión.
 */
final class AuthTestBrowser
{
    public readonly Application $app;

    /**
     * @var array<string, string>
     */
    private array $cookies = [];

    public function __construct()
    {
        $this->app = createApp();
    }

    /**
     * @param array<string, mixed> $body
     */
    public function send(string $method, string $uri, array $body = []): Response
    {
        $response = $this->app->run(new Request($method, $uri, [], $body, [], ['REMOTE_ADDR' => '203.0.113.10'], $this->cookies));

        foreach ($response->getCookies() as $name => $cookie) {
            $this->cookies[$name] = (string) $cookie['value'];
        }

        return $response;
    }

    public function register(): Response
    {
        return $this->send('POST', '/registro', [
            'nombre' => 'Ana',
            'email' => 'Ana@Ejemplo.com',
            'password' => 'secreto-largo',
            'password_confirmation' => 'secreto-largo',
        ]);
    }

    /**
     * Correos enviados (MAIL_DRIVER=array en phpunit.xml).
     *
     * @return list<Message>
     */
    public function sentMail(): array
    {
        $mailer = $this->app->make(Mailer::class);

        return $mailer instanceof ArrayMailer ? $mailer->sent() : [];
    }
}

function browser(): AuthTestBrowser
{
    return new AuthTestBrowser();
}

it('registra una cuenta e inicia sesión', function (): void {
    $browser = browser();

    expect($browser->send('GET', '/registro')->getStatusCode())->toBe(200);

    $response = $browser->register();

    expect($response->getHeader('location'))->toBe('/panel')
        ->and(User::where('email', 'ana@ejemplo.com')->exists())->toBeTrue()
        ->and($browser->send('GET', '/panel')->getBody())->toContain('Hola, Ana');
});

it('muestra los errores de validación con el input anterior', function (): void {
    $browser = browser();
    $browser->send('GET', '/registro');
    $response = $browser->send('POST', '/registro', ['nombre' => 'Ana', 'email' => 'no-es-correo', 'password' => 'corta']);

    expect($response->getHeader('location'))->toBe('/registro');

    $form = $browser->send('GET', '/registro')->getBody();

    expect($form)->toContain('El campo email debe ser un correo electrónico válido.')
        ->and($form)->toContain('El campo password debe tener al menos 8 caracteres.')
        ->and($form)->toContain('value="Ana"')
        ->and($form)->not->toContain('value="corta"');
});

it('no permite registrar dos veces el mismo correo', function (): void {
    $browser = browser();
    $browser->register();
    $browser->send('POST', '/logout');
    $browser->send('GET', '/registro');
    $browser->register();

    expect($browser->send('GET', '/registro')->getBody())->toContain('Ya existe un registro con ese email.');
});

it('inicia y cierra sesión', function (): void {
    $browser = browser();
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-largo')]);

    expect($browser->send('GET', '/panel')->getHeader('location'))->toBe('/login');

    $browser->send('GET', '/login');
    $login = $browser->send('POST', '/login', ['email' => 'ana@ejemplo.com', 'password' => 'secreto-largo']);

    expect($login->getHeader('location'))->toBe('/panel')
        ->and($browser->send('GET', '/login')->getHeader('location'))->toBe('/panel');

    $logout = $browser->send('POST', '/logout');

    expect($logout->getHeader('location'))->toBe('/')
        ->and($browser->send('GET', '/panel')->getHeader('location'))->toBe('/login');
});

it('rechaza credenciales incorrectas y bloquea tras 5 intentos', function (): void {
    $browser = browser();
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-largo')]);
    $browser->send('GET', '/login');

    for ($i = 0; $i < 5; $i++) {
        $browser->send('POST', '/login', ['email' => 'ana@ejemplo.com', 'password' => 'incorrecta']);
    }

    expect($browser->send('GET', '/login')->getBody())->toContain('El correo o la contraseña no son correctos.');

    $browser->send('POST', '/login', ['email' => 'ana@ejemplo.com', 'password' => 'secreto-largo']);

    expect($browser->send('GET', '/login')->getBody())->toContain('Demasiados intentos.');
});

it('recupera la contraseña con el enlace del correo', function (): void {
    $browser = browser();
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('vieja-clave')]);

    $browser->send('GET', '/recuperar-contrasena');
    $browser->send('POST', '/recuperar-contrasena', ['email' => 'ana@ejemplo.com']);

    $sent = $browser->sentMail();
    preg_match('#/restablecer-contrasena/([a-f0-9]{64})\?email=#', $sent[0]->text ?? '', $match);
    $token = $match[1] ?? '';

    expect($sent)->toHaveCount(1)
        ->and($token)->not->toBe('')
        ->and($browser->send('GET', '/recuperar-contrasena')->getBody())->toContain('te enviamos un enlace')
        ->and($browser->send('GET', '/restablecer-contrasena/' . $token . '?email=ana%40ejemplo.com')->getBody())
        ->toContain('value="ana@ejemplo.com"');

    $response = $browser->send('POST', '/restablecer-contrasena', [
        'token' => $token,
        'email' => 'ana@ejemplo.com',
        'password' => 'nueva-clave-larga',
        'password_confirmation' => 'nueva-clave-larga',
    ]);

    expect($response->getHeader('location'))->toBe('/login')
        ->and(Hash::check('nueva-clave-larga', User::where('email', 'ana@ejemplo.com')->firstOrFail()->password))->toBeTrue();
});

it('no revela si el correo existe y rechaza tokens inválidos', function (): void {
    $browser = browser();
    $browser->send('GET', '/recuperar-contrasena');
    $browser->send('POST', '/recuperar-contrasena', ['email' => 'nadie@ejemplo.com']);

    expect($browser->sentMail())->toBe([])
        ->and($browser->send('GET', '/recuperar-contrasena')->getBody())->toContain('te enviamos un enlace');

    $browser->send('GET', '/restablecer-contrasena/falso');
    $browser->send('POST', '/restablecer-contrasena', [
        'token' => 'falso',
        'email' => 'nadie@ejemplo.com',
        'password' => 'nueva-clave-larga',
        'password_confirmation' => 'nueva-clave-larga',
    ]);

    expect($browser->send('GET', '/restablecer-contrasena/falso')->getBody())->toContain('El enlace no es válido o ya venció.');
});
