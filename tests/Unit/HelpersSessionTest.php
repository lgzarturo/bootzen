<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->sessions = makeTempDir('bootzen-sessions');
    session_save_path($this->sessions);
});

afterEach(function (): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    $_SESSION = [];
    removeDir($this->sessions);
});

it('genera un token CSRF estable por sesión y lo valida', function (): void {
    $token = csrf_token();

    expect($token)->toMatch('/^[a-f0-9]{64}$/')
        ->and(csrf_token())->toBe($token)
        ->and(csrf_field())->toBe('<input type="hidden" name="_csrf_token" value="' . $token . '"/>')
        ->and(validate_csrf_token($token))->toBeTrue()
        ->and(validate_csrf_token('otro'))->toBeFalse()
        ->and(validate_csrf_token(null))->toBeFalse();
});

it('rechaza tokens si la sesión no tiene uno', function (): void {
    session_start();
    $_SESSION = [];

    expect(validate_csrf_token('cualquiera'))->toBeFalse();
});

it('recupera el valor anterior de un campo desde la sesión', function (): void {
    session_start();
    $_SESSION['_old_input'] = ['email' => 'ana@ejemplo.com'];

    expect(old('email'))->toBe('ana@ejemplo.com')
        ->and(old('nombre', 'sin nombre'))->toBe('sin nombre');
});
