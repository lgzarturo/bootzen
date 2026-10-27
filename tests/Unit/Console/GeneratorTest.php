<?php

declare(strict_types=1);

use BootZen\Console\Generator;
use BootZen\Console\ResourceField;
use BootZen\Support\Str;
use BootZen\View\ViewCompiler;

beforeEach(function (): void {
    $this->project = makeTempDir('bootzen-gen');
    writeFile($this->project . '/routes/web.php', (string) file_get_contents(dirname(__DIR__, 3) . '/skeleton/routes/web.php'));
    $this->generator = new Generator($this->project);
    $this->read = fn (string $path): string => (string) file_get_contents($this->project . '/' . $path);
});

afterEach(fn () => removeDir($this->project));

/**
 * Verifica que un archivo PHP generado no tenga errores de sintaxis.
 */
function assertValidPhp(string $file): void
{
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
    expect($status)->toBe(0, implode("\n", $output));
}

it('convierte nombres y pluraliza en inglés', function (string $word, string $plural): void {
    expect(Str::plural($word))->toBe($plural);
})->with([
    ['Task', 'Tasks'], ['Category', 'Categories'], ['OrderItem', 'OrderItems'], ['Box', 'Boxes'],
    ['Person', 'People'], ['Status', 'Statuses'], ['Day', 'Days'], ['Data', 'Data'], ['order_item', 'order_items'],
]);

it('convierte entre formatos de nombre', function (): void {
    expect(Str::studly('order_item'))->toBe('OrderItem')
        ->and(Str::studly('order item'))->toBe('OrderItem')
        ->and(Str::snake('OrderItem'))->toBe('order_item')
        ->and(Str::kebab('OrderItem'))->toBe('order-item')
        ->and(Str::camel('order_item'))->toBe('orderItem')
        ->and(Str::headline('starts_at'))->toBe('Starts at');
});

it('genera la rebanada completa de make:resource', function (): void {
    $files = $this->generator->resource('Task', ['title:string', 'done:bool', 'notes:text:nullable', 'due:date:nullable']);

    expect($files)->toHaveCount(9)
        ->and($files[0])->toMatch('#^database/migrations/\d{4}_\d{2}_\d{2}_\d{6}_crear_tasks\.php$#')
        ->and(end($files))->toBe('routes/web.php (ruta agregada)');

    foreach (array_slice($files, 0, 3) as $file) {
        assertValidPhp($this->project . '/' . $file);
    }
    assertValidPhp($this->project . '/tests/Feature/TaskTest.php');
    assertValidPhp($this->project . '/routes/web.php');

    $compiler = new ViewCompiler();
    foreach (['index', 'create', 'edit', 'show'] as $view) {
        $path = $this->project . "/resources/views/tasks/{$view}.php";
        $compiled = $this->project . "/compiled-{$view}.php";
        file_put_contents($compiled, $compiler->compile((string) file_get_contents($path), $path));
        assertValidPhp($compiled);
    }

    expect(($this->read)($files[0]))->toContain('title TEXT NOT NULL')
        ->toContain('done INTEGER NOT NULL DEFAULT 0')
        ->toContain('notes TEXT NULL')
        ->and(($this->read)('app/Models/Task.php'))->toContain("protected static string \$table = 'tasks';")
        ->toContain("protected static array \$fillable = ['title', 'done', 'notes', 'due'];")
        ->toContain("protected static array \$casts = ['done' => 'bool', 'due' => 'date'];")
        ->toContain(' * @property \DateTimeImmutable|null $due')
        ->and(($this->read)('app/Controllers/TaskController.php'))->toContain("'title' => 'required|string|max:255',")
        ->toContain("'done' => 'boolean',")
        ->toContain("'notes' => 'nullable|string',")
        ->and(($this->read)('resources/views/tasks/edit.php'))->toContain(':value="$task->title"')
        ->toContain(':checked="$task->done"')
        ->toContain(":value=\"\$task->due?->format('Y-m-d')\"")
        ->and(($this->read)('resources/views/tasks/create.php'))->not->toContain('$task')
        ->and(($this->read)('tests/Feature/TaskTest.php'))->toContain("->assertSessionHasErrors(['title'])")
        ->and(($this->read)('routes/web.php'))->toContain("    \$router->resource('tasks', TaskController::class);\n};")
        ->toContain("use App\\Controllers\\HomeController;\nuse App\\Controllers\\TaskController;\nuse BootZen\\Core\\Router;");
});

it('usa la tabla indicada, SQL de MySQL y nombres compuestos', function (): void {
    (new Generator($this->project, 'mysql'))->resource('OrderItem', ['name:string', 'starts_at:datetime', 'price:float'], 'pedidos_items');

    expect(($this->read)('app/Models/OrderItem.php'))->toContain("'pedidos_items'")
        ->and((string) file_get_contents((string) glob($this->project . '/database/migrations/*.php')[0]))
        ->toContain('id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY')
        ->toContain('name VARCHAR(255) NOT NULL')
        ->toContain('starts_at DATETIME NOT NULL')
        ->toContain('price DOUBLE NOT NULL')
        ->toContain('created_at DATETIME NULL')
        ->and(($this->read)('resources/views/pedidos-items/index.php'))->toContain('$pedidosItems as $orderItem')
        ->and(($this->read)('tests/Feature/OrderItemTest.php'))->not->toContain('assertSessionHasErrors([\'starts_at\'])')
        ->and(($this->read)('routes/web.php'))->toContain("\$router->resource('pedidos-items', OrderItemController::class);");
});

it('usa --table también para rutas, vistas y variables', function (): void {
    $this->generator->resource('Ciudad', ['nombre:string'], 'ciudades');

    expect(($this->read)('routes/web.php'))->toContain("\$router->resource('ciudades', CiudadController::class);")
        ->and($this->project . '/resources/views/ciudades/index.php')->toBeFile()
        ->and(($this->read)('app/Controllers/CiudadController.php'))->toContain("view('ciudades.index', ['ciudades' => \$ciudades])")
        ->and(($this->read)('resources/views/ciudades/index.php'))->toContain('<x-layouts.app title="Ciudades">');
});

it('valida los campos de make:resource', function (array $fields, string $message): void {
    expect(fn () => $this->generator->resource('Task', $fields))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'sin campos' => [[], 'Indica al menos un campo'],
    'tipo' => [['title:texto'], 'Tipo [texto] desconocido'],
    'nombre' => [['Title:string'], 'Campo inválido [Title]'],
    'reservado' => [['id:int'], 'ya lo crea el generador'],
    'modificador' => [['title:string:unique'], 'Modificador [unique] desconocido'],
    'repetido' => [['title:string', 'title:text'], 'campos repetidos'],
]);

it('acepta alias de tipos y trata bool como no nulo', function (): void {
    expect(ResourceField::parse('n:integer')->type)->toBe('int')
        ->and(ResourceField::parse('p:decimal')->type)->toBe('float')
        ->and(ResourceField::parse('a:boolean:nullable')->nullable)->toBeFalse()
        ->and(ResourceField::parse('title')->type)->toBe('string');
});

it('no sobrescribe archivos ni deja la rebanada a medias', function (): void {
    writeFile($this->project . '/app/Models/Task.php', '<?php // propio');

    expect(fn () => $this->generator->resource('Task', ['title:string']))->toThrow(RuntimeException::class, 'Ya existe app/Models/Task.php')
        ->and(glob($this->project . '/database/migrations/*.php'))->toBe([])
        ->and(fn () => $this->generator->model('Task'))->toThrow(RuntimeException::class, 'Ya existe');
});

it('pide editar las rutas a mano si routes/web.php no tiene la forma esperada', function (): void {
    file_put_contents($this->project . '/routes/web.php', '<?php return [];');

    $this->generator->resource('Task', ['title:string']);
})->throws(RuntimeException::class, "Agrega a mano: \$router->resource('tasks', TaskController::class);");

it('genera controlador, modelo, migración, middleware, seeder, test y componentes', function (): void {
    $files = [
        ...$this->generator->controller('factura'),
        ...$this->generator->model('Cliente', 'clientes', true),
        ...$this->generator->migration('agregar_telefono_a_clientes'),
        ...$this->generator->migration('create_pagos_table'),
        ...$this->generator->middleware('SoloAdmins'),
        ...$this->generator->seeder('ClientesSeeder'),
        ...$this->generator->test('Factura'),
        ...$this->generator->test('Calculo', true),
        ...$this->generator->component('forms.select', true),
        ...$this->generator->component('modal'),
    ];

    expect($files)->toContain('app/Controllers/FacturaController.php')
        ->toContain('resources/views/factura/index.php')
        ->toContain('app/Models/Cliente.php')
        ->toContain('app/Middleware/SoloAdmins.php')
        ->toContain('database/seeds/ClientesSeeder.php')
        ->toContain('tests/Feature/FacturaTest.php')
        ->toContain('tests/Unit/CalculoTest.php')
        ->toContain('resources/views/components/forms/select.php')
        ->toContain('app/View/Components/Forms/Select.php')
        ->toContain('resources/views/components/modal.php');

    foreach ($files as $file) {
        if (str_ends_with($file, '.php') && ! str_starts_with($file, 'resources/')) {
            assertValidPhp($this->project . '/' . $file);
        }
    }

    $migrations = array_map('basename', glob($this->project . '/database/migrations/*.php') ?: []);

    expect($migrations)->toHaveCount(3)
        ->and(implode(' ', $migrations))->toContain('crear_clientes')->toContain('agregar_telefono_a_clientes')->toContain('create_pagos_table')
        ->and(($this->read)('app/View/Components/Forms/Select.php'))->toContain('namespace App\View\Components\Forms;')
        ->and(($this->read)('app/Controllers/FacturaController.php'))->toContain("return view('factura.index');");

    // Los nombres tienen prefijos distintos y en orden aunque se creen en el mismo segundo
    expect(count(array_unique(array_map(static fn (string $file): string => substr($file, 0, 17), $migrations))))->toBe(3);

    foreach (glob($this->project . '/database/migrations/*') ?: [] as $migration) {
        expect((string) file_get_contents($migration))
            ->toContain(str_contains($migration, 'agregar_telefono') ? '// $db->exec' : 'CREATE TABLE');
    }
});

it('rechaza nombres inválidos', function (Closure $generate, string $message): void {
    expect(fn () => $generate($this->generator))->toThrow(InvalidArgumentException::class, $message);
})->with([
    [fn (Generator $g) => $g->controller('1Factura'), 'Nombre inválido'],
    [fn (Generator $g) => $g->component('Modal'), 'Nombre de componente inválido'],
    [fn (Generator $g) => $g->model('Cliente', 'Clientes-X'), 'Nombre de tabla inválido'],
]);

it('falla con un mensaje claro si falta una plantilla', function (): void {
    (new Generator($this->project, 'sqlite', $this->project . '/sin-stubs'))->seeder('Uno');
})->throws(RuntimeException::class, 'No se encontró la plantilla');
