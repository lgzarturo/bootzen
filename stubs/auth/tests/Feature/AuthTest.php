<?php

declare(strict_types=1);

use App\Models\User;
use BootZen\Auth\Hash;
use BootZen\Mail\ArrayMailer;
use BootZen\Mail\Mailer;
use BootZen\Mail\Message;
use BootZen\Testing\TestClient;

/**
 * Datos de registro válidos.
 *
 * @return array<string, string>
 */
function registro(): array
{
    return [
        'nombre' => 'Ana',
        'email' => 'Ana@Ejemplo.com',
        'password' => 'secreto-largo',
        'password_confirmation' => 'secreto-largo',
    ];
}

/**
 * Página de inicio tras el login (config/auth.php, home).
 */
function inicio(): string
{
    $home = config('auth.home', '/');

    return is_string($home) ? $home : '/';
}

/**
 * Correos enviados (MAIL_DRIVER=array en phpunit.xml).
 *
 * @return list<Message>
 */
function correosEnviados(TestClient $client): array
{
    $mailer = $client->app->make(Mailer::class);

    return $mailer instanceof ArrayMailer ? $mailer->sent() : [];
}

it('registra una cuenta e inicia sesión', function (): void {
    $client = client();
    $client->get('/registro')->assertOk();

    $client->post('/registro', registro())->assertRedirect(inicio());

    $client->assertAuthenticated()->assertDatabaseHas('users', ['email' => 'ana@ejemplo.com']);
    $client->get('/panel')->assertSee('Hola, Ana');
});

it('muestra los errores de validación con el input anterior', function (): void {
    $client = client();

    $client->from('/registro')->post('/registro', ['nombre' => 'Ana', 'email' => 'no-es-correo', 'password' => 'corta'])
        ->assertRedirect('/registro')
        ->assertSessionHasErrors(['email', 'password']);

    $client->get('/registro')
        ->assertSee('El campo email debe ser un correo electrónico válido.')
        ->assertSee('El campo password debe tener al menos 8 caracteres.')
        ->assertSee('value="Ana"', false)
        ->assertDontSee('value="corta"', false);
});

it('no permite registrar dos veces el mismo correo', function (): void {
    $client = client();
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-largo')]);

    $client->from('/registro')->post('/registro', registro())->assertSessionHasErrors(['email']);
    $client->get('/registro')->assertSee('Ya existe un registro con ese email.');
});

it('inicia y cierra sesión', function (): void {
    $client = client();
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-largo')]);

    $client->get('/panel')->assertRedirect('/login');
    // Vuelve a la página que intentó abrir antes del login
    $client->post('/login', ['email' => 'ana@ejemplo.com', 'password' => 'secreto-largo'])->assertRedirect('/panel');
    $client->get('/login')->assertRedirect(inicio());

    $client->post('/logout')->assertRedirect('/');
    $client->assertGuest()->get('/panel')->assertRedirect('/login');
});

it('rechaza credenciales incorrectas y bloquea tras 5 intentos', function (): void {
    $client = client()->from('/login');
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('secreto-largo')]);

    for ($i = 0; $i < 5; $i++) {
        $client->post('/login', ['email' => 'ana@ejemplo.com', 'password' => 'incorrecta'])->assertRedirect('/login');
    }

    $client->get('/login')->assertSee('El correo o la contraseña no son correctos.');
    $client->post('/login', ['email' => 'ana@ejemplo.com', 'password' => 'secreto-largo'])->assertSessionHasErrors(['email']);
    $client->assertGuest()->get('/login')->assertSee('Demasiados intentos.');
});

it('recupera la contraseña con el enlace del correo', function (): void {
    $client = client();
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('vieja-clave')]);

    $client->from('/recuperar-contrasena')->post('/recuperar-contrasena', ['email' => 'ana@ejemplo.com'])
        ->assertRedirect('/recuperar-contrasena')
        ->assertSessionHas('status');

    $sent = correosEnviados($client);
    preg_match('#/restablecer-contrasena/([a-f0-9]{64})\?email=#', $sent[0]->text ?? '', $match);
    $token = $match[1] ?? '';

    expect($sent)->toHaveCount(1)->and($token)->not->toBe('');

    $client->get('/restablecer-contrasena/' . $token . '?email=ana%40ejemplo.com')->assertSee('value="ana@ejemplo.com"', false);
    $client->post('/restablecer-contrasena', [
        'token' => $token,
        'email' => 'ana@ejemplo.com',
        'password' => 'nueva-clave-larga',
        'password_confirmation' => 'nueva-clave-larga',
    ])->assertRedirect('/login');

    expect(Hash::check('nueva-clave-larga', User::where('email', 'ana@ejemplo.com')->firstOrFail()->password))->toBeTrue();
});

it('no revela si el correo existe y rechaza tokens inválidos', function (): void {
    $client = client();

    $client->post('/recuperar-contrasena', ['email' => 'nadie@ejemplo.com'])->assertSessionHas('status');
    expect(correosEnviados($client))->toBe([]);

    $client->from('/restablecer-contrasena/falso')->post('/restablecer-contrasena', [
        'token' => 'falso',
        'email' => 'nadie@ejemplo.com',
        'password' => 'nueva-clave-larga',
        'password_confirmation' => 'nueva-clave-larga',
    ])->assertSessionHasErrors(['email']);

    $client->get('/restablecer-contrasena/falso')->assertSee('El enlace no es válido o ya venció.');
});
