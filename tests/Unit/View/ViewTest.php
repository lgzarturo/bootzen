<?php

declare(strict_types=1);

use BootZen\Core\Container;
use BootZen\Core\HttpException;
use BootZen\View\Component;
use BootZen\View\View;
use BootZen\View\ViewException;

final class ViewTestReloj
{
    public function hora(): string
    {
        return '12:00';
    }
}

/**
 * Componente con clase para <x-view-test-alerta> (namespace vacío en los tests).
 */
final class ViewTestAlerta extends Component
{
    public string $color;

    public function __construct(public string $tipo = 'info', public ?ViewTestReloj $reloj = null)
    {
        $this->color = $tipo === 'error' ? 'rojo' : 'azul';
    }

    public function data(): array
    {
        return ['hora' => $this->reloj?->hora()];
    }
}

final class ViewTestObligatorio extends Component
{
    public function __construct(public int $total)
    {
    }
}

final class ViewTestNoComponente
{
}

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-views');
    $this->views = $this->project . '/resources/views';
    $this->view = new View($this->views, $this->project . '/storage/cache/views', new Container(), '');
});

afterEach(function (): void {
    removeDir($this->project);
});

function viewFile(string $views, string $name, string $contents): void
{
    writeFile($views . '/' . str_replace('.', '/', $name) . '.php', $contents);
}

it('renderiza vistas con datos y datos compartidos', function (): void {
    viewFile($this->views, 'tareas.index', '<h1>{{ $titulo }}</h1><p>{{ $usuario }}</p>');
    $this->view->share('usuario', 'Ana');

    expect($this->view->render('tareas.index', ['titulo' => 'Mis <tareas>']))
        ->toBe('<h1>Mis &lt;tareas&gt;</h1><p>Ana</p>')
        ->and($this->view->exists('tareas.index'))->toBeTrue()
        ->and($this->view->exists('tareas.otra'))->toBeFalse()
        ->and($this->view->getShared())->toHaveKey('usuario');
});

it('explica qué archivo crear si la vista o el componente no existen', function (): void {
    viewFile($this->views, 'pagina', '<x-boton />');

    expect(fn () => $this->view->render('no.existe'))->toThrow(ViewException::class, 'Crea el archivo resources/views/no/existe.php')
        ->and(fn () => $this->view->render('pagina'))->toThrow(ViewException::class, 'El componente <x-boton> no existe');
});

it('renderiza componentes anidados con slots, props y atributos', function (): void {
    viewFile($this->views, 'components.layouts.app', "@props(['titulo'])\n<title>{{ \$titulo }}</title><main>{{ \$slot }}</main>");
    viewFile($this->views, 'components.card', "@props(['titulo' => null])\n<div {{ \$attributes->merge(['class' => 'card']) }}>@if (\$titulo)<h2>{{ \$titulo }}</h2>@endif{{ \$slot }}@if (isset(\$pie))<footer>{{ \$pie }}</footer>@endif</div>");
    viewFile($this->views, 'pagina', '<x-layouts.app titulo="Inicio"><x-card titulo="Uno" class="extra" :data-n="1 + 1">Hola <b>{{ $nombre }}</b><x-slot:pie>Fin</x-slot:pie></x-card></x-layouts.app>');

    expect($this->view->render('pagina', ['nombre' => 'Ana']))
        ->toBe('<title>Inicio</title><main><div class="card extra" data-n="2"><h2>Uno</h2>Hola <b>Ana</b><footer>Fin</footer></div></main>');
});

it('falla si falta una prop obligatoria o se usa @props fuera de un componente', function (): void {
    viewFile($this->views, 'components.layouts.app', "@props(['titulo'])\n{{ \$titulo }}");
    viewFile($this->views, 'sin-titulo', '<x-layouts.app />');
    viewFile($this->views, 'suelta', "@props(['x'])\n");

    expect(fn () => $this->view->render('sin-titulo'))->toThrow(ViewException::class, 'Falta la prop obligatoria [titulo]')
        ->and(fn () => $this->view->render('suelta'))->toThrow(ViewException::class, '@props solo se puede usar');
});

it('acepta props en kebab-case para nombres en camelCase', function (): void {
    viewFile($this->views, 'components.titulo', "@props(['textoPrincipal'])\n{{ \$textoPrincipal }}");
    viewFile($this->views, 'pagina', '<x-titulo texto-principal="Hola" />');

    expect($this->view->render('pagina'))->toBe('Hola');
});

it('usa la clase del componente para preparar los datos', function (): void {
    viewFile($this->views, 'components.view-test-alerta', '<p {{ $attributes }}>{{ $tipo }} {{ $color }} {{ $hora }}: {{ $slot }}</p>');
    viewFile($this->views, 'pagina', '<x-view-test-alerta tipo="error" id="a1">Ups</x-view-test-alerta>');

    expect($this->view->render('pagina'))->toBe('<p id="a1">error rojo 12:00: Ups</p>');
});

it('valida las clases de componente', function (string $class, string $tag, string $message): void {
    viewFile($this->views, 'components.' . $class, 'x');
    viewFile($this->views, 'pagina', $tag);

    expect(fn () => $this->view->render('pagina'))->toThrow(ViewException::class, $message);
})->with([
    'atributo obligatorio' => ['view-test-obligatorio', '<x-view-test-obligatorio />', 'Falta el atributo [total]'],
    'no extiende Component' => ['view-test-no-componente', '<x-view-test-no-componente />', 'debe extender'],
]);

it('reporta errores de ejecución con la línea de la vista original', function (): void {
    viewFile($this->views, 'rota', "<p>uno</p>\n<p>dos</p>\n{{ throw new RuntimeException('boom') }}\n");

    try {
        $this->view->render('rota');
        $this->fail('Se esperaba ViewException');
    } catch (ViewException $e) {
        expect($e->getMessage())->toBe('boom (en la vista resources/views/rota.php:3)')
            ->and($e->getViewFile())->toBe($this->views . '/rota.php')
            ->and($e->getViewLine())->toBe(3)
            ->and($e->getPrevious())->toBeInstanceOf(RuntimeException::class);
    }
});

it('deja pasar las HttpException lanzadas desde una vista', function (): void {
    viewFile($this->views, 'privada', '{{ abort(403) }}');

    $this->view->render('privada');
})->throws(HttpException::class);

it('rechaza imprimir arrays y simula métodos válidos', function (): void {
    viewFile($this->views, 'array', '{{ $lista }}');
    viewFile($this->views, 'metodo', "@method('get')");
    viewFile($this->views, 'form', "@method('put')");

    expect(fn () => $this->view->render('array', ['lista' => [1]]))->toThrow(ViewException::class, 'tipo array')
        ->and(fn () => $this->view->render('metodo'))->toThrow(ViewException::class, '@method solo acepta')
        ->and($this->view->render('form'))->toBe('<input type="hidden" name="_method" value="PUT">');
});

it('muestra errores de validación compartidos con @error', function (): void {
    // @error va pegado al @enderror anterior: una directiva puede seguir a otra sin espacio
    viewFile($this->views, 'form', "@error('email')<span>{{ \$message }}</span>@enderror@error('nombre')<b>x</b>@enderror");
    $this->view->share('errors', ['email' => ['Correo inválido', 'Otro'], 'nombre' => []]);

    expect($this->view->render('form'))->toBe('<span>Correo inválido</span>');
});

it('detecta componentes sin cerrar y slots fuera de componentes', function (): void {
    viewFile($this->views, 'components.card', '{{ $slot }}');
    viewFile($this->views, 'abierto', '<x-card>sin cierre');
    viewFile($this->views, 'slot', '<x-slot:pie>x</x-slot:pie>');

    expect(fn () => $this->view->render('abierto'))->toThrow(ViewException::class, 'El componente <x-card> no se cerró')
        ->and(fn () => $this->view->render('slot'))->toThrow(ViewException::class, 'solo se puede usar dentro de un componente');
});

it('recompila cuando cambia la vista y permite precompilar y limpiar la caché', function (): void {
    viewFile($this->views, 'saludo', 'Hola');
    viewFile($this->views, 'components.card', '{{ $slot }}');

    expect($this->view->render('saludo'))->toBe('Hola');

    viewFile($this->views, 'saludo', 'Adiós');
    touch($this->views . '/saludo.php', time() + 10);

    expect($this->view->render('saludo'))->toBe('Adiós')
        ->and($this->view->compileAll())->toBe(2)
        ->and($this->view->clearCache())->toBe(2)
        ->and(glob($this->project . '/storage/cache/views/*.php'))->toBe([]);
});
