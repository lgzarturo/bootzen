<?php

declare(strict_types=1);

use BootZen\View\ComponentAttributeBag;

it('imprime atributos escapados, booleanos y arrays', function (): void {
    $bag = new ComponentAttributeBag([
        'title' => 'A "B" <c>',
        'disabled' => true,
        'hidden' => false,
        'nada' => null,
        'data-props' => ['id' => 1, 'nombre' => 'Peña'],
    ]);

    expect((string) $bag)->toBe('title="A &quot;B&quot; &lt;c&gt;" disabled data-props="{&quot;id&quot;:1,&quot;nombre&quot;:&quot;Peña&quot;}"');
});

it('suma clases y estilos y deja que los atributos recibidos reemplacen al resto', function (): void {
    $bag = (new ComponentAttributeBag(['class' => 'shadow', 'style' => 'color: red', 'type' => 'submit']))
        ->merge(['class' => 'rounded p-4', 'style' => 'margin: 0', 'type' => 'button', 'role' => 'alert']);

    expect($bag->all())->toBe([
        'class' => 'rounded p-4 shadow',
        'style' => 'margin: 0; color: red',
        'type' => 'submit',
        'role' => 'alert',
    ]);
});

it('agrega clases condicionales', function (): void {
    $bag = (new ComponentAttributeBag(['class' => 'extra']))->class(['p-4', 'bg-red-50' => true, 'hidden' => false]);

    expect($bag->get('class'))->toBe('p-4 bg-red-50 extra')
        ->and(ComponentAttributeBag::classList('a b'))->toBe('a b');
});

it('filtra, consulta y recorre atributos', function (): void {
    $bag = new ComponentAttributeBag(['id' => 'x', 'class' => 'y', 'title' => 'z']);

    expect($bag->only(['id'])->all())->toBe(['id' => 'x'])
        ->and($bag->except(['id', 'class'])->all())->toBe(['title' => 'z'])
        ->and($bag->has('class'))->toBeTrue()
        ->and($bag->get('falta', 'def'))->toBe('def')
        ->and(iterator_to_array($bag))->toBe(['id' => 'x', 'class' => 'y', 'title' => 'z']);
});
