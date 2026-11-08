<?php

declare(strict_types=1);

use App\Models\User;
use BootZen\Auth\Hash;
use BootZen\Mail\ArrayMailer;
use BootZen\Mail\Mailer;
use BootZen\Mail\MailException;
use BootZen\Mail\Message;
use BootZen\Testing\TestClient;
use Database\Factories\UserFactory;

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
    return home_url();
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

/**
 * Path del enlace de verificación del último correo enviado.
 */
function enlaceDeVerificacion(TestClient $client): string
{
    $correos = correosEnviados($client);
    $texto = end($correos) instanceof Message ? end($correos)->text : '';
    preg_match('#https?://[^/\s]+(/\S*verificar-correo/\S+)#', $texto, $match);

    return $match[1] ?? '';
}

it('registra una cuenta y pide verificar el correo', function (): void {
    $client = client();
    $client->get('/registro')->assertOk();

    $client->post('/registro', registro())->assertRedirect('/verificar-correo');

    $client->assertAuthenticated()->assertDatabaseHas('users', ['email' => 'ana@ejemplo.com', 'email_verified_at' => null]);
    $client->get('/panel')->assertRedirect('/verificar-correo');
    $client->get('/verificar-correo')->assertOk()->assertSee('Te enviamos un enlace a ana@ejemplo.com.');

    $enlace = enlaceDeVerificacion($client);
    expect($enlace)->toStartWith('/verificar-correo/1/');

    $client->get($enlace)->assertRedirect(inicio())->assertSessionHas('status', 'Tu correo quedó verificado.');
    $client->get('/panel')->assertOk()->assertSee('Hola, Ana');
    $client->get('/verificar-correo')->assertRedirect(inicio());
});

it('verifica con el enlace aunque no haya sesión y rechaza enlaces alterados', function (): void {
    $client = client();
    $client->post('/registro', registro());
    $enlace = enlaceDeVerificacion($client);
    $client->post('/logout');

    $client->get(str_replace('/verificar-correo/1/', '/verificar-correo/2/', $enlace))->assertForbidden();
    $client->get(preg_replace('/signature=[a-f0-9]+/', 'signature=' . str_repeat('0', 64), $enlace) ?? '')->assertForbidden();
    $client->get($enlace)->assertRedirect('/login')->assertSessionHas('status', 'Tu correo quedó verificado. Ya puedes iniciar sesión.');

    expect(User::where('email', 'ana@ejemplo.com')->firstOrFail()->email_verified_at)->not->toBeNull();
});

it('reenvía el enlace con límite', function (): void {
    $client = client();
    $client->actingAs(User::factory()->sinVerificar()->create());

    for ($i = 0; $i < 3; $i++) {
        $client->from('/verificar-correo')->post('/verificar-correo')->assertRedirect('/verificar-correo');
    }

    expect(correosEnviados($client))->toHaveCount(3);
    $client->post('/verificar-correo')->assertStatus(429);
});

it('con recordarme sigue dentro al cerrar el navegador', function (): void {
    $client = client();
    User::factory()->create(['email' => 'ana@ejemplo.com']);

    $client->post('/login', ['email' => 'ana@ejemplo.com', 'password' => UserFactory::PASSWORD, 'remember' => '1'])->assertRedirect(inicio());
    $client->closeBrowser()->get('/panel')->assertOk();

    // Sin recordarme, cerrar el navegador cierra la sesión
    $otro = client();
    User::factory()->create(['email' => 'beto@ejemplo.com']);
    $otro->post('/login', ['email' => 'beto@ejemplo.com', 'password' => UserFactory::PASSWORD]);
    $otro->closeBrowser()->get('/panel')->assertRedirect('/login');
});

it('cierra la sesión en los demás dispositivos', function (): void {
    $client = client();
    $ana = User::factory()->create(['email' => 'ana@ejemplo.com']);
    $otro = new TestClient($client->app);
    $otro->post('/login', ['email' => 'ana@ejemplo.com', 'password' => UserFactory::PASSWORD, 'remember' => '1']);
    $client->actingAs($ana);

    $client->from('/panel')->post('/sesiones/cerrar-otras', ['password' => 'incorrecta'])->assertSessionHasErrors(['password']);
    $client->from('/panel')->post('/sesiones/cerrar-otras', ['password' => UserFactory::PASSWORD])
        ->assertRedirect('/panel')
        ->assertSessionHas('status', 'Cerraste la sesión en tus otros dispositivos.');

    $client->get('/panel')->assertOk();
    $otro->get('/panel')->assertRedirect('/login');
    $otro->closeBrowser()->get('/panel')->assertRedirect('/login');
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
    User::factory()->create(['email' => 'ana@ejemplo.com']);

    $client->get('/panel')->assertRedirect('/login');
    // Vuelve a la página que intentó abrir antes del login
    $client->post('/login', ['email' => 'ana@ejemplo.com', 'password' => 'secreto-largo'])->assertRedirect('/panel');
    $client->get('/login')->assertRedirect(inicio());

    $client->post('/logout')->assertRedirect('/');
    $client->assertGuest()->get('/panel')->assertRedirect('/login');
});

it('rechaza credenciales incorrectas y bloquea tras 5 intentos', function (): void {
    $client = client()->from('/login');
    User::factory()->create(['email' => 'ana@ejemplo.com']);

    for ($i = 0; $i < 5; $i++) {
        $client->post('/login', ['email' => 'ana@ejemplo.com', 'password' => 'incorrecta'])->assertRedirect('/login');
    }

    $client->get('/login')->assertSee('El correo o la contraseña no son correctos.');
    $client->post('/login', ['email' => 'ana@ejemplo.com', 'password' => 'secreto-largo'])->assertSessionHasErrors(['email']);
    $client->assertGuest()->get('/login')->assertSee('Demasiados intentos.');
});

it('recupera la contraseña con el enlace del correo', function (): void {
    $client = client();
    User::factory()->sinVerificar()->create(['email' => 'ana@ejemplo.com']);

    // Un "recordarme" en otro equipo deja de servir al cambiar la contraseña
    $otro = new TestClient($client->app);
    $otro->post('/login', ['email' => 'ana@ejemplo.com', 'password' => UserFactory::PASSWORD, 'remember' => '1']);

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

    $ana = User::where('email', 'ana@ejemplo.com')->firstOrFail();
    expect(Hash::check('nueva-clave-larga', $ana->password))->toBeTrue()
        ->and($ana->email_verified_at)->not->toBeNull();
    $otro->closeBrowser()->get('/panel')->assertRedirect('/login');
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

it('responde lo mismo si el correo de recuperación no se pudo enviar', function (): void {
    $client = client();
    User::create(['nombre' => 'Ana', 'email' => 'ana@ejemplo.com', 'password' => Hash::make('vieja-clave')]);
    $client->app->instance(Mailer::class, new class () implements Mailer {
        public function send(Message $message): void
        {
            throw new MailException('El servidor SMTP rechazó autenticación: 535 credenciales inválidas');
        }
    });

    $client->from('/recuperar-contrasena')->post('/recuperar-contrasena', ['email' => 'ana@ejemplo.com'])
        ->assertRedirect('/recuperar-contrasena')
        ->assertSessionHas('status', 'Si el correo está registrado, te enviamos un enlace para cambiar la contraseña.');
});

it('atiende en inglés con el prefijo /en', function (): void {
    $client = client();
    $inicioEs = inicio();
    $inicio = '/en' . $inicioEs;

    $client->post('/en/registro', registro())->assertRedirect('/en/verificar-correo');
    $client->get('/en/verificar-correo')->assertSee('Verify your email');
    expect(correosEnviados($client)[0]->subject ?? '')->toBe('Verify your email');
    $client->get(enlaceDeVerificacion($client))->assertRedirect($inicio);
    $client->get($inicio)
        ->assertSee('<html lang="en">', false)
        ->assertSee('hreflang="es" href="' . url($inicioEs) . '"', false);
    $client->get('/en/panel')->assertSee('Hi, Ana');

    $client->post('/en/logout')->assertRedirect('/en');
    $client->assertGuest()->get('/en/panel')->assertRedirect('/en/login');

    $client->from('/en/login')->post('/en/login', ['email' => 'ana@ejemplo.com', 'password' => 'incorrecta']);
    $client->get('/en/login')->assertSee('The email or password is incorrect.')->assertSee('Forgot your password?');
});
