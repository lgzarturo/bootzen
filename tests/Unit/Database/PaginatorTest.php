<?php

declare(strict_types=1);

use BootZen\Database\Paginator;

it('calcula páginas y enlaces conservando la query string', function (): void {
    $page = (new Paginator(['a', 'b'], 7, 2, 2))->appends(['q' => 'pan', 'orden' => 'fecha']);

    expect([$page->lastPage(), $page->previousPage(), $page->nextPage(), $page->hasPages(), $page->isEmpty()])
        ->toBe([4, 1, 3, true, false])
        ->and($page->url(3))->toBe('?q=pan&orden=fecha&page=3')
        ->and($page->url(0))->toBe('?q=pan&orden=fecha&page=1')
        ->and(count($page))->toBe(2)
        ->and(iterator_to_array($page))->toBe(['a', 'b']);
});

it('maneja la primera y la última página y el caso vacío', function (): void {
    expect((new Paginator([], 0, 10, 1))->lastPage())->toBe(1)
        ->and((new Paginator([], 0, 10, 1))->previousPage())->toBeNull()
        ->and((new Paginator(['x'], 1, 10, 1))->nextPage())->toBeNull()
        ->and((new Paginator([], 0, 10, 1))->hasPages())->toBeFalse();
});

it('se serializa a JSON con los datos de paginación', function (): void {
    $json = json_encode(new Paginator([['id' => 1]], 11, 10, 1));

    expect(json_decode((string) $json, true))->toBe([
        'data' => [['id' => 1]],
        'total' => 11,
        'per_page' => 10,
        'current_page' => 1,
        'last_page' => 2,
    ]);
});
