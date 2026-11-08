<?php

declare(strict_types=1);

use BootZen\Support\Fake;

it('con la misma semilla repite la misma secuencia', function (): void {
    $serie = fn (Fake $fake): array => [$fake->name(), $fake->email(), $fake->sentence(), $fake->number(1, 1000), $fake->date(), $fake->uuid()];

    $a = $serie(new Fake(42));

    expect($serie(new Fake(42)))->toBe($a)
        ->and($serie((new Fake(7))->seed(42)))->toBe($a)
        ->and($serie(new Fake(43)))->not->toBe($a);
});

it('da datos en español o en inglés según el idioma', function (): void {
    $locale = 'es';
    $fake = new Fake(1, function () use (&$locale): string {
        return $locale;
    });
    $es = [$fake->firstName(), $fake->city(), $fake->phoneNumber()];
    $locale = 'en';
    $en = [$fake->firstName(), $fake->city(), $fake->phoneNumber()];

    expect($es[2])->toMatch('/^55 \d{4} \d{4}$/')
        ->and($en[2])->toMatch('/^\(\d{3}\) \d{3}-\d{4}$/')
        ->and(['Ana', 'Luis', 'María', 'Carlos', 'Lucía', 'Jorge', 'Sofía', 'Miguel', 'Valeria', 'Diego', 'Camila', 'Andrés', 'Fernanda', 'Javier', 'Daniela', 'Ricardo', 'Paula', 'Héctor', 'Regina', 'Emilio', 'Ximena', 'Arturo', 'Mariana', 'Raúl'])->toContain($es[0])
        ->and($en[1])->toBeIn(['New York', 'Chicago', 'Austin', 'Denver', 'Seattle', 'Boston', 'Portland', 'Miami', 'Atlanta', 'Phoenix']);
});

it('respeta rangos, longitudes y formatos', function (): void {
    $fake = new Fake(3);

    foreach (range(1, 200) as $_) {
        expect($fake->number(5, 9))->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(9)
            ->and($fake->decimal(1, 2, 2))->toBeGreaterThanOrEqual(1.0)->toBeLessThanOrEqual(2.0)
            ->and(mb_strlen($fake->text(60)))->toBeLessThanOrEqual(60)
            ->and($fake->date('2026-01-01', '2026-01-31'))->toStartWith('2026-01-');
    }

    expect($fake->uuid())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($fake->dateTime('2026-03-01 10:00', '2026-03-01 11:00'))->toStartWith('2026-03-01 1')
        ->and($fake->email())->not->toMatch('/[áéíóúñ]/')
        ->and($fake->sentence())->toMatch('/^\p{Lu}/u')
        ->and($fake->paragraph(2))->toEndWith('.')
        ->and($fake->words(4))->toHaveCount(4)
        ->and($fake->randomElement(['a' => 'x']))->toBe('x')
        ->and($fake->url())->toStartWith('https://example.')
        ->and($fake->boolean(0))->toBeFalse()
        ->and($fake->boolean(100))->toBeTrue()
        ->and([$fake->company(), $fake->address(), $fake->country(), $fake->lastName(), $fake->userName(), $fake->word()])->each->toBeString()->not->toBe('');
});

it('unique() no repite y avisa cuando se acaban los valores', function (): void {
    $fake = new Fake(9);
    $valores = array_map(fn (): int => $fake->unique()->number(1, 20), range(1, 20));
    sort($valores);

    expect($valores)->toBe(range(1, 20))
        // Sin unique() puede repetir
        ->and(fn () => $fake->unique()->number(1, 20))->toThrow(InvalidArgumentException::class, 'unique() ya no encuentra valores distintos')
        ->and(fn () => $fake->randomElement([]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $fake->date('ayer no', 'now'))->toThrow(InvalidArgumentException::class, 'Rango de fechas inválido');
});

it('fake() usa la semilla fija en tests y el idioma activo', function (): void {
    $proyecto = makeTempDir('bootzen-fake');
    writeFile($proyecto . '/config/app.php', "<?php return ['env' => 'testing', 'locale' => 'en', 'locales' => 'en,es'];");

    $nombres = function () use ($proyecto): array {
        new BootZen\Core\Application($proyecto);

        return [fake()->name(), fake()->name()];
    };

    $primera = $nombres();

    expect($nombres())->toBe($primera)
        ->and(explode(' ', $primera[0])[1])->toBeIn(['Smith', 'Johnson', 'Brown', 'Taylor', 'Miller', 'Wilson', 'Moore', 'Clark', 'Walker', 'Hall', 'Young', 'King', 'Wright', 'Hill', 'Green', 'Baker', 'Adams', 'Nelson']);

    removeDir($proyecto);
});
