<?php

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Session\ArraySessionStore;
use BootZen\Session\Session;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-helpers-session');
    $this->app = new Application($this->project);
});

afterEach(fn () => removeDir($this->project));

it('funciona sin sesión activa con valores vacíos', function (): void {
    expect(csrf_token())->toBe('')
        ->and(old('email', 'def'))->toBe('def')
        ->and(session('status', 'nada'))->toBe('nada')
        ->and(validate_csrf_token('x'))->toBeFalse()
        ->and(back()->getHeader('location'))->toBe('/');
});

it('lanza un error claro al pedir la sesión sin el grupo web', function (): void {
    session();
})->throws(LogicException::class, 'grupo de middleware web');

it('usa la sesión activa del contenedor', function (): void {
    $session = new Session(new ArraySessionStore(), Session::generateId(), [
        'status' => 'Listo',
        '_old_input' => ['email' => 'ana@ejemplo.com'],
        '_previous_url' => '/registro',
    ]);
    $this->app->instance(Session::class, $session);
    $token = csrf_token();

    expect(session())->toBe($session)
        ->and(session('status'))->toBe('Listo')
        ->and(old('email'))->toBe('ana@ejemplo.com')
        ->and($token)->toMatch('/^[a-f0-9]{64}$/')
        ->and(csrf_field())->toBe('<input type="hidden" name="_token" value="' . $token . '">')
        ->and(validate_csrf_token($token))->toBeTrue()
        ->and(validate_csrf_token('otro'))->toBeFalse()
        ->and(validate_csrf_token(null))->toBeFalse()
        ->and(back()->getHeader('location'))->toBe('/registro');
});
