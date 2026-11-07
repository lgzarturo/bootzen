<?php

declare(strict_types=1);

use BootZen\Translation\Formatter;
use BootZen\Translation\Translator;

beforeEach(function (): void {
    $this->dir = makeTempDir('bootzen-lang');
    writeFile($this->dir . '/framework/en.php', "<?php return ['El campo :attribute es obligatorio.' => 'The :attribute field is required.', 'Siguiente' => 'Next'];");
    writeFile($this->dir . '/app/en.php', "<?php return ['Hola, :nombre' => 'Hi, :nombre', 'Siguiente' => 'Continue', 'una tarea|:count tareas' => 'one task|:count tasks', '{0} ninguna|una|:count' => '{0} none|one|:count'];");
    writeFile($this->dir . '/app/en/tienda.php', "<?php return ['Carrito' => 'Cart'];");
    $this->translator = new Translator('es', 'es', ['es', 'en'], [$this->dir . '/framework', $this->dir . '/app']);
});

afterEach(fn () => removeDir($this->dir));

it('usa el texto en español como clave y traduce al idioma activo', function (): void {
    expect($this->translator->get('Hola, :nombre', ['nombre' => 'Ana']))->toBe('Hola, Ana');

    $this->translator->setLocale('en');

    expect($this->translator->get('Hola, :nombre', ['nombre' => 'Ana']))->toBe('Hi, Ana')
        ->and($this->translator->get('El campo :attribute es obligatorio.', ['attribute' => 'email']))->toBe('The email field is required.')
        ->and($this->translator->get('Siguiente'))->toBe('Continue')
        ->and($this->translator->get('Carrito'))->toBe('Cart')
        ->and($this->translator->get('Texto sin traducir'))->toBe('Texto sin traducir')
        ->and($this->translator->get('Hola, :nombre', ['nombre' => 'Ana'], 'es'))->toBe('Hola, Ana');
});

it('elige singular, plural y cero', function (): void {
    expect($this->translator->choice('una tarea|:count tareas', 1))->toBe('una tarea')
        ->and($this->translator->choice('una tarea|:count tareas', 5))->toBe('5 tareas')
        ->and($this->translator->choice('{0} ninguna|una|:count', 0))->toBe('ninguna')
        ->and($this->translator->choice('{0} ninguna|una|:count', 1))->toBe('una')
        ->and($this->translator->choice('{0} ninguna|una|:count', 7))->toBe('7');

    $this->translator->setLocale('en');

    expect($this->translator->choice('una tarea|:count tareas', 2))->toBe('2 tasks')
        ->and($this->translator->choice('{0} ninguna|una|:count', 0))->toBe('none');
});

it('reemplaza los marcadores largos antes que los cortos', function (): void {
    expect($this->translator->get(':nombre_completo (:nombre)', ['nombre' => 'Ana', 'nombre_completo' => 'Ana López']))->toBe('Ana López (Ana)');
});

it('solo acepta idiomas permitidos y archivos que retornan un array', function (): void {
    writeFile($this->dir . '/app/fr.php', '<?php return "hola";');

    expect(fn () => $this->translator->setLocale('de'))->toThrow(InvalidArgumentException::class, 'El idioma [de] no está en APP_LOCALES (es, en).')
        ->and(fn () => new Translator('fr', 'fr', ['es'], []))->toThrow(InvalidArgumentException::class, 'APP_LOCALE [fr] no está en APP_LOCALES')
        ->and(fn () => (new Translator('fr', 'fr', ['fr'], [$this->dir . '/app']))->get('x'))->toThrow(InvalidArgumentException::class, 'debe retornar un array');
});

it('formatea números, moneda y fechas con y sin intl', function (bool $intl): void {
    $mx = new Formatter('es_MX', 'MXN', $intl, 'UTC');
    $us = new Formatter('en_US', 'USD', $intl, 'UTC');
    $es = new Formatter('es_ES', 'EUR', $intl, 'UTC');
    $date = new DateTimeImmutable('2026-11-07 15:30:00', new DateTimeZone('UTC'));

    expect($mx->number(1234567.891, 2))->toBe('1,234,567.89')
        ->and($es->number(1234567.891, 2))->toBe('1.234.567,89')
        ->and($mx->money(1234.5))->toBe('$1,234.50')
        ->and($us->money(-1234.5))->toContain('1,234.50')
        ->and($mx->date($date, 'long'))->toBe('7 de noviembre de 2026')
        ->and($us->date($date, 'long'))->toBe('November 7, 2026')
        ->and($mx->date('2026-11-07', 'short'))->toMatch('#^0?7/11/(20)?26$#')
        ->and($us->date($date, 'medium', true))->toContain('2026')
        ->and(fn () => $mx->date($date, 'full'))->toThrow(InvalidArgumentException::class, 'Estilo de fecha [full]');
})->with(['con intl' => [true], 'sin intl' => [false]]);
