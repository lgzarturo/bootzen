<?php

declare(strict_types=1);

use BootZen\View\ViewCompiler;
use BootZen\View\ViewException;

function compileView(string $source): string
{
    return (new ViewCompiler())->compile($source, 'resources/views/prueba.php');
}

it('compila cada sintaxis de la lista cerrada', function (string $source): void {
    expect(compileView($source))->toMatchSnapshot();
})->with([
    'echo escapado' => ['<p>{{ $nombre }}</p>'],
    'echo sin escapar' => ['<div>{!! $html !!}</div>'],
    'comentario' => ["a{{-- nota\nlarga --}}b"],
    'echo literal para JS' => ['<script>const t = `@{{ valor }}`;</script>'],
    'if elseif else' => ["@if (\$a > 1)\nuno\n@elseif (\$b)\ndos\n@else\ntres\n@endif"],
    'foreach' => ["@foreach (\$items as \$item)\n{{ \$item }}\n@endforeach"],
    'foreach con empty' => ["@foreach (\$items as \$item)\n{{ \$item }}\n@empty\nnada\n@endforeach"],
    'csrf y method' => ["<form>\n@csrf\n@method('DELETE')\n</form>"],
    'error' => ["@error('email')\n<p>{{ \$message }}</p>\n@enderror"],
    'props' => ["@props(['titulo' => null, 'tipo'])\n<h1>{{ \$titulo }}</h1>"],
    'componente con atributos' => ['<x-card title="Hola {{ $nombre }}" :items="$lista" disabled data-id=\'7\'>contenido</x-card>'],
    'componente autocerrado en subcarpeta' => ['<x-layouts.app-shell :user="$user" />'],
    'slot con nombre' => ["<x-card>\n<x-slot:footer>pie</x-slot:footer>\n</x-card>"],
]);

it('conserva el número de líneas para que los errores apunten a la vista original', function (string $source): void {
    expect(substr_count(compileView($source), "\n"))->toBe(substr_count($source, "\n"));
})->with([
    'echo multilínea' => ["{{ implode(', ',\n  \$items) }}\nfin"],
    'componente multilínea' => ["<x-card\n    title=\"x\"\n    :items=\"\$a\"\n>\nhola\n</x-card>\n"],
    'directiva multilínea' => ["@if (\$a\n  && \$b)\nsí\n@endif\n"],
    'comentario multilínea' => ["{{-- a\nb\nc --}}\nfin"],
]);

it('imprime el salto de línea que sigue a un echo', function (): void {
    $output = (static function (string $code): string {
        $__env = new class () {
            public function escape(mixed $value): string
            {
                return (string) $value;
            }
        };
        ob_start();
        eval('?>' . $code);

        return (string) ob_get_clean();
    })(compileView("{{ 'a' }}\n{{ 'b' }}\n"));

    expect($output)->toBe("a\nb\n");
});

it('deja intactos los @ que no son directivas', function (): void {
    $source = "<style>@media (max-width: 600px) {}</style>\ncorreo@if.com @usuario @endforech";

    expect(compileView($source))->toBe($source);
});

it('detecta errores de anidamiento con la línea de la vista', function (string $source, string $message): void {
    expect(fn () => compileView($source))->toThrow(ViewException::class, $message);
})->with([
    'if sin cerrar' => ["a\n@if (\$x)\nb", 'falta @endif para el @if abierto'],
    'endif sobrante' => ["@endif", '@endif sin un @if abierto'],
    'empty fuera de foreach' => ["@if (\$x)\n@empty\n@endif", '@empty sin un @foreach abierto'],
    'cruce de bloques' => ["@foreach (\$a as \$b)\n@if (\$b)\n@endforeach", '(resources/views/prueba.php:3)'],
    'sin paréntesis' => ['@if $x', '@if necesita argumentos entre paréntesis'],
    'paréntesis abierto' => ["@if (\$x\n", 'falta cerrar el paréntesis de @if'],
    'atributo enlazado vacío' => ["\n<x-card :items=\"\" />", 'el atributo :items necesita una expresión PHP'],
]);

it('no toma como directiva una @ pegada a una letra o número', function (): void {
    expect(compileView('soporte@empty.com x@endif'))->toBe('soporte@empty.com x@endif')
        ->and(compileView("@if (\$a)<b>1</b>@endif@if (\$b)2\n@endif"))->toContain('<?php endif; ?><?php if ($b): ?>');
});

it('respeta paréntesis y comillas dentro de los argumentos', function (): void {
    expect(compileView("@if (in_array(')', \$a) && \$b === \"(\")\nx\n@endif"))
        ->toContain("<?php if (in_array(')', \$a) && \$b === \"(\"): ?>");
});
