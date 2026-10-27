<?php

declare(strict_types=1);

use BootZen\Session\ArraySessionStore;
use BootZen\Session\FileSessionStore;
use BootZen\Session\Session;

it('guarda, lee, borra y extrae valores', function (): void {
    $session = new Session(new ArraySessionStore(), Session::generateId());
    $session->put('carrito', [3, 7]);
    $session->put(['tema' => 'oscuro', 'vacio' => null]);

    expect($session->get('carrito'))->toBe([3, 7])
        ->and($session->get('falta', 'def'))->toBe('def')
        ->and($session->has('tema'))->toBeTrue()
        ->and($session->has('vacio'))->toBeFalse()
        ->and($session->pull('tema'))->toBe('oscuro')
        ->and($session->has('tema'))->toBeFalse();

    $session->forget('carrito');

    expect($session->all())->toBe(['vacio' => null]);
});

it('mantiene los datos flash solo durante la siguiente petición', function (): void {
    $session = new Session(new ArraySessionStore(), Session::generateId());
    $session->flash('status', 'Guardado');

    $session->ageFlashData(); // fin de la petición que hizo flash
    expect($session->get('status'))->toBe('Guardado');

    $session->ageFlashData(); // fin de la petición siguiente
    expect($session->get('status'))->toBeNull();
});

it('conserva un flash que se vuelve a guardar en la petición siguiente', function (): void {
    $session = new Session(new ArraySessionStore(), Session::generateId());
    $session->flash('status', 'uno');
    $session->ageFlashData();
    $session->flash('status', 'dos');
    $session->ageFlashData();

    expect($session->get('status'))->toBe('dos');
});

it('genera el token CSRF al pedirlo y lo mantiene estable', function (): void {
    $session = new Session(new ArraySessionStore(), Session::generateId());

    expect($session->shouldPersist())->toBeFalse();

    $token = $session->token();

    expect($token)->toMatch('/^[a-f0-9]{64}$/')
        ->and($session->token())->toBe($token)
        ->and($session->shouldPersist())->toBeTrue()
        ->and($session->regenerateToken())->not->toBe($token);
});

it('cambia el id al regenerar, conserva los datos y borra la sesión vieja', function (): void {
    $store = new ArraySessionStore();
    $session = new Session($store, Session::generateId());
    $session->put('usuario', 5);
    $session->save();
    $viejo = $session->getId();

    $session->regenerate();

    expect($session->getId())->not->toBe($viejo)
        ->and(Session::isValidId($session->getId()))->toBeTrue()
        ->and($session->get('usuario'))->toBe(5)
        ->and($store->read($viejo))->toBeNull();
});

it('borra todo al invalidar', function (): void {
    $session = new Session(new ArraySessionStore(), Session::generateId(), ['usuario' => 5], true);
    $token = $session->token();
    $session->invalidate();

    expect(array_keys($session->all()))->toBe(['_token'])
        ->and($session->token())->not->toBe($token)
        ->and($session->shouldPersist())->toBeTrue();
});

it('solo persiste sesiones existentes o con datos', function (): void {
    $nueva = new Session(new ArraySessionStore(), Session::generateId());
    $nueva->ageFlashData();

    expect($nueva->shouldPersist())->toBeFalse()
        ->and((new Session(new ArraySessionStore(), Session::generateId(), [], true))->shouldPersist())->toBeTrue();
});

it('expone el input anterior, los errores y la URL previa', function (): void {
    $session = new Session(new ArraySessionStore(), Session::generateId(), [
        '_old_input' => ['email' => 'ana@ejemplo.com', 'items' => [['precio' => '10']]],
        '_errors' => ['email' => ['Correo inválido.']],
        '_previous_url' => '/registro',
    ]);

    expect($session->getOldInput('email'))->toBe('ana@ejemplo.com')
        ->and($session->getOldInput('items.0.precio'))->toBe('10')
        ->and($session->getOldInput('nombre', 'x'))->toBe('x')
        ->and($session->errors())->toBe(['email' => ['Correo inválido.']])
        ->and($session->previousUrl())->toBe('/registro');
});

it('valida el formato del id', function (string $id, bool $valid): void {
    expect(Session::isValidId($id))->toBe($valid);
})->with([
    'generado' => [str_repeat('a1', 20), true],
    'corto' => ['abc', false],
    'ruta' => ['../../etc/passwd', false],
    'mayúsculas' => [str_repeat('A', 40), false],
]);

describe('FileSessionStore', function (): void {
    beforeEach(fn () => $this->dir = makeTempDir('bootzen-sessions'));
    afterEach(fn () => removeDir($this->dir));

    it('escribe, lee y borra sesiones en archivos', function (): void {
        $store = new FileSessionStore($this->dir . '/nuevo');
        $store->write('abc', ['a' => 1]);

        expect($store->read('abc'))->toBe(['a' => 1])
            ->and(is_file($this->dir . '/nuevo/sess_abc'))->toBeTrue()
            ->and($store->read('otra'))->toBeNull();

        $store->destroy('abc');

        expect($store->read('abc'))->toBeNull();
    });

    it('descarta sesiones expiradas y las limpia con gc', function (): void {
        $store = new FileSessionStore($this->dir, 60);
        $store->write('vieja', ['a' => 1]);
        $store->write('nueva', ['b' => 2]);
        touch($this->dir . '/sess_vieja', time() - 120);

        expect($store->read('vieja'))->toBeNull();

        touch($this->dir . '/sess_nueva', time() - 120);
        $store->write('otra', []);

        expect($store->gc(60))->toBe(1)
            ->and($store->read('otra'))->toBe([]);
    });

    it('no restaura objetos ni datos corruptos', function (): void {
        $store = new FileSessionStore($this->dir);
        file_put_contents($this->dir . '/sess_obj', serialize(['fecha' => new DateTimeImmutable()]));
        file_put_contents($this->dir . '/sess_roto', 'no es serialize');

        expect($store->read('obj')['fecha'])->toBeInstanceOf(__PHP_Incomplete_Class::class)
            ->and($store->read('roto'))->toBeNull();
    });

    it('falla con un mensaje claro si no puede crear la carpeta', function (): void {
        file_put_contents($this->dir . '/archivo', '');

        (new FileSessionStore($this->dir . '/archivo/sub'))->write('abc', []);
    })->throws(RuntimeException::class, 'No se pudo crear el directorio de sesiones');
});

it('guarda en memoria con ArraySessionStore', function (): void {
    $store = new ArraySessionStore();
    $store->write('a', ['x' => 1]);

    expect($store->read('a'))->toBe(['x' => 1])
        ->and($store->gc(10))->toBe(0);

    $store->destroy('a');

    expect($store->read('a'))->toBeNull();
});
