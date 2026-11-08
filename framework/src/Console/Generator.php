<?php

/**
 * Generator.php
 *
 * Generadores de bin/bootzen (make:*): crean archivos a partir de las plantillas de framework/stubs.
 * make:resource arma la rebanada completa: migración, modelo, controlador con validación, rutas,
 * vistas y tests. Nunca sobrescribe un archivo existente.
 *
 * @package    BootZen\Console
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Console;

use BootZen\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Crea los archivos y devuelve sus rutas relativas al proyecto.
 *
 * @example $generator->resource('Task', ['title:string', 'done:bool']);
 */
final class Generator
{
    /**
     * @param string $basePath Raíz del proyecto
     * @param string $driver Driver de la base de datos (sqlite o mysql) para el SQL de las migraciones
     * @param string|null $stubs Carpeta de plantillas; por defecto framework/stubs
     */
    public function __construct(
        private readonly string $basePath,
        private readonly string $driver = 'sqlite',
        private ?string $stubs = null
    ) {
        $this->stubs ??= dirname(__DIR__, 2) . '/stubs';
    }

    /**
     * Controlador con un método index y su vista; con $json, sin vista y respondiendo JSON.
     *
     * @return list<string>
     */
    public function controller(string $name, bool $json = false): array
    {
        $class = self::className($name, 'Controller');
        $base = substr($class, 0, -strlen('Controller'));
        $view = Str::kebab($base !== '' ? $base : $class);

        if ($json) {
            return [$this->write("app/Controllers/{$class}.php", $this->render('controller.json', [
                'Class' => $class,
                'Description' => "Controlador {$class}.",
            ]))];
        }

        return [
            $this->write("app/Controllers/{$class}.php", $this->render('controller', [
                'Class' => $class,
                'Description' => "Controlador {$class}.",
                'view' => $view . '.index',
            ])),
            $this->write("resources/views/{$view}/index.php", $this->render('view', [
                'Title' => Str::headline($base !== '' ? $base : $class),
                'Class' => $class,
                'path' => $view . '/index',
            ])),
        ];
    }

    /**
     * Modelo (y su migración si $migration es true).
     *
     * @return list<string>
     */
    public function model(string $name, ?string $table = null, bool $migration = false): array
    {
        $class = self::className($name);
        $table = $this->tableName($class, $table);
        $files = [$this->write("app/Models/{$class}.php", $this->renderModel($class, $table, []))];

        if ($migration) {
            $files[] = $this->createTableMigration($table, []);
        }

        return $files;
    }

    /**
     * Migración. Si el nombre es crear_<tabla> o create_<tabla>_table, trae el CREATE TABLE.
     *
     * @return list<string>
     */
    public function migration(string $name): array
    {
        $name = preg_match('/^[a-z0-9_]+$/', $name) === 1 ? $name : Str::snake($name);

        if (preg_match('/^(?:crear_(\w+)|create_(\w+?)_table)$/', $name, $match) === 1) {
            return [$this->createTableMigration($match[1] !== '' ? $match[1] : $match[2], [], $name)];
        }

        return [$this->write($this->migrationPath($name), $this->render('migration', []))];
    }

    /**
     * Migración con las tablas de la cola (jobs y failed_jobs), para QUEUE_DRIVER=database.
     *
     * @return list<string>
     * @throws RuntimeException Si ya existe una migración de la cola
     */
    public function queueTable(): array
    {
        if (glob($this->basePath . '/database/migrations/*_crear_tablas_de_cola.php') !== []) {
            throw new RuntimeException('Ya existe la migración de la cola en database/migrations. Ejecuta php bin/bootzen migrate.');
        }

        return [$this->write($this->migrationPath('crear_tablas_de_cola'), $this->render('queue.migration', []))];
    }

    /**
     * Job en app/Jobs para encolar con dispatch().
     *
     * @return list<string>
     */
    public function job(string $name): array
    {
        $class = self::className($name);

        return [$this->write("app/Jobs/{$class}.php", $this->render('job', ['Class' => $class]))];
    }

    /**
     * @return list<string>
     */
    public function middleware(string $name): array
    {
        $class = self::className($name);

        return [$this->write("app/Middleware/{$class}.php", $this->render('middleware', [
            'Class' => $class,
            'alias' => Str::kebab($class),
        ]))];
    }

    /**
     * Factory en database/factories para Modelo::factory(). Con $fields (de make:resource) trae un
     * valor falso por campo.
     *
     * @param list<ResourceField> $fields
     * @return list<string>
     */
    public function factory(string $name, array $fields = []): array
    {
        $model = self::className(preg_replace('/Factory$/', '', $name) ?? $name);
        $definition = $fields === []
            ? "            // 'nombre' => fake()->name(),"
            : implode("\n", array_map(static fn (ResourceField $field): string => "            '{$field->name}' => {$field->fake()},", $fields));

        return [$this->write("database/factories/{$model}Factory.php", $this->render('factory', ['Model' => $model, 'definition' => $definition]))];
    }

    /**
     * Política de un modelo en app/Policies (Gate la encuentra por el nombre).
     *
     * @return list<string>
     */
    public function policy(string $name): array
    {
        $model = self::className(preg_replace('/Policy$/', '', $name) ?? $name);

        return [$this->write("app/Policies/{$model}Policy.php", $this->render('policy', ['Model' => $model, 'variable' => Str::camel($model)]))];
    }

    /**
     * @return list<string>
     */
    public function seeder(string $name): array
    {
        $class = self::className($name);

        return [$this->write("database/seeds/{$class}.php", $this->render('seeder', ['Class' => $class]))];
    }

    /**
     * @return list<string>
     */
    public function test(string $name, bool $unit = false): array
    {
        $class = self::className($name, 'Test');

        return [$this->write(
            ($unit ? 'tests/Unit/' : 'tests/Feature/') . $class . '.php',
            $this->render($unit ? 'test.unit' : 'test', [])
        )];
    }

    /**
     * Componente de vista <x-nombre> (con clase si $withClass).
     *
     * @return list<string>
     */
    public function component(string $name, bool $withClass = false): array
    {
        if (preg_match('/^[a-z][a-z0-9-]*(\.[a-z][a-z0-9-]*)*$/', $name) !== 1) {
            throw new InvalidArgumentException("Nombre de componente inválido [{$name}]: usa minúsculas y guiones, con puntos para carpetas (forms.select).");
        }

        $path = str_replace('.', '/', $name);
        $files = [$this->write("resources/views/components/{$path}.php", $this->render('component', ['tag' => $name]))];

        if ($withClass) {
            $class = implode('\\', array_map(Str::studly(...), explode('.', $name)));
            $files[] = $this->write('app/View/Components/' . str_replace('\\', '/', $class) . '.php', $this->render('component.class', [
                'Class' => Str::studly(basename($path)),
                'tag' => $name,
                'path' => $path,
            ]));

            if (str_contains($class, '\\')) {
                // La clase vive en un subnamespace: se ajusta el namespace del archivo generado
                $file = $this->basePath . '/' . end($files);
                $namespace = 'App\\View\\Components\\' . substr($class, 0, (int) strrpos($class, '\\'));
                file_put_contents($file, str_replace('namespace App\\View\\Components;', "namespace {$namespace};", (string) file_get_contents($file)));
            }
        }

        return $files;
    }

    /**
     * Rebanada vertical: migración, modelo, controlador, rutas, vistas y tests.
     * El plural sale de las reglas del inglés (Task => tasks); $table lo reemplaza en todo.
     *
     * @param list<string> $definitions Campos 'nombre:tipo[:nullable]'
     * @return list<string> Archivos creados o modificados
     */
    public function resource(string $name, array $definitions, ?string $table = null): array
    {
        if ($definitions === []) {
            throw new InvalidArgumentException('Indica al menos un campo: make:resource Task title:string done:bool');
        }

        $model = self::className($name);
        $fields = array_map(ResourceField::parse(...), $definitions);
        $names = array_map(static fn (ResourceField $field): string => $field->name, $fields);

        if (count($names) !== count(array_unique($names))) {
            throw new InvalidArgumentException('Hay campos repetidos en la lista.');
        }

        // Con --table, la tabla también da nombre a las rutas, las vistas y la variable en plural
        $plural = $table !== null ? Str::studly($table) : Str::plural($model);
        $table = $this->tableName($model, $table);
        $route = Str::kebab($plural);
        $vars = [
            'Model' => $model,
            'route' => $route,
            'view' => $route,
            'table' => $table,
            'singular' => Str::camel($model),
            'plural' => Str::camel($plural),
            'camel' => Str::camel($model),
            'Title' => Str::headline($plural),
            'Heading' => Str::headline($model),
        ];

        // Se valida todo antes de escribir para no dejar la rebanada a medias
        $targets = [
            "app/Models/{$model}.php",
            "app/Controllers/{$model}Controller.php",
            "tests/Feature/{$model}Test.php",
            "database/factories/{$model}Factory.php",
            ...array_map(static fn (string $view): string => "resources/views/{$route}/{$view}.php", ['index', 'create', 'edit', 'show']),
        ];
        foreach ($targets as $target) {
            if (file_exists($this->basePath . '/' . $target)) {
                throw new RuntimeException("Ya existe {$target}. Bórralo o elige otro nombre.");
            }
        }

        $files = [
            $this->createTableMigration($table, $fields),
            $this->write("app/Models/{$model}.php", $this->renderModel($model, $table, $fields, true)),
            $this->write("app/Controllers/{$model}Controller.php", $this->render('resource/controller', $vars + [
                'rules' => implode("\n", array_map(
                    static fn (ResourceField $field): string => "            '{$field->name}' => '{$field->rules()}',",
                    $fields
                )),
            ])),
        ];

        foreach (['index', 'create', 'edit', 'show'] as $view) {
            $files[] = $this->write("resources/views/{$route}/{$view}.php", $this->render('resource/' . $view, $vars + $this->viewParts($fields, $vars['singular'])));
        }

        $files[] = $this->factory($model, $fields)[0];
        $files[] = $this->write("tests/Feature/{$model}Test.php", $this->render('resource/test', $vars + $this->testParts($fields, $vars)));
        $files[] = $this->addResourceRoute($route, $model . 'Controller');

        return $files;
    }

    /**
     * Panel de administración de un modelo: controlador en app/Controllers/Admin, vistas en
     * resources/views/admin, test y rutas en el grupo /admin (auth, verified y role:admin). Si el modelo
     * no existe, crea también su migración, modelo y factory. Necesita el scaffold --auth.
     *
     * @param list<string> $definitions Campos como en make:resource
     * @return list<string>
     * @throws InvalidArgumentException Si faltan campos o se repiten
     * @throws RuntimeException Si el proyecto no tiene --auth o algún archivo ya existe
     */
    public function admin(string $name, array $definitions, ?string $table = null): array
    {
        if (! is_file($this->basePath . '/app/Models/User.php') || ! is_file($this->basePath . '/routes/auth.php')) {
            throw new RuntimeException('make:admin necesita usuarios y el rol admin del scaffold --auth. Crea el proyecto con: bootzen-new mi-app --auth');
        }

        if ($definitions === []) {
            throw new InvalidArgumentException('Indica los campos: make:admin Producto nombre:string precio:float');
        }

        $model = self::className($name);
        $fields = array_map(ResourceField::parse(...), $definitions);
        $names = array_map(static fn (ResourceField $field): string => $field->name, $fields);

        if (count($names) !== count(array_unique($names))) {
            throw new InvalidArgumentException('Hay campos repetidos en la lista.');
        }

        $plural = $table !== null ? Str::studly($table) : Str::plural($model);
        $table = $this->tableName($model, $table);
        $route = Str::kebab($plural);
        $modelExists = is_file($this->basePath . "/app/Models/{$model}.php");
        $vars = [
            'Model' => $model,
            'route' => $route,
            'view' => $route,
            'table' => $table,
            'singular' => Str::camel($model),
            'plural' => Str::camel($plural),
            'Title' => Str::headline($plural),
            'Heading' => Str::headline($model),
        ];

        $targets = [
            "app/Controllers/Admin/{$model}Controller.php",
            "tests/Feature/Admin{$model}Test.php",
            ...array_map(static fn (string $view): string => "resources/views/admin/{$route}/{$view}.php", ['index', 'create', 'edit']),
            ...($modelExists ? [] : ["database/factories/{$model}Factory.php"]),
        ];
        foreach ($targets as $target) {
            if (file_exists($this->basePath . '/' . $target)) {
                throw new RuntimeException("Ya existe {$target}. Bórralo o elige otro nombre.");
            }
        }

        $files = [];

        if (! $modelExists) {
            $files[] = $this->createTableMigration($table, $fields);
            $files[] = $this->write("app/Models/{$model}.php", $this->renderModel($model, $table, $fields, true));
            $files[] = $this->factory($model, $fields)[0];
        }

        $searchable = array_values(array_filter($fields, static fn (ResourceField $field): bool => in_array($field->type, ['string', 'email', 'text'], true)));
        $filterable = array_values(array_filter($fields, static fn (ResourceField $field): bool => in_array($field->type, ['bool', 'date', 'datetime'], true)));
        $sortable = array_values(array_filter($fields, static fn (ResourceField $field): bool => $field->type !== 'text'));
        $list = static fn (array $items): string => '[' . implode(', ', array_map(static fn (string $item): string => "'{$item}'", $items)) . ']';

        $files[] = $this->write("app/Controllers/Admin/{$model}Controller.php", $this->render('admin/controller', $vars + [
            'rules' => implode("\n", array_map(static fn (ResourceField $field): string => "            '{$field->name}' => '{$field->rules()}',", $fields)),
            'csvColumns' => implode("\n", array_map(static fn (ResourceField $field): string => "            '{$field->name}' => __('{$field->label()}'),", $fields)),
            'searchable' => $list(array_map(static fn (ResourceField $field): string => $field->name, $searchable)),
            'filters' => '[' . implode(', ', array_map(static fn (ResourceField $field): string => "'{$field->name}' => '" . ($field->type === 'bool' ? 'bool' : 'date') . "'", $filterable)) . ']',
            'sortable' => $list(['id', ...array_map(static fn (ResourceField $field): string => $field->name, $sortable), 'created_at']),
        ]));

        $listed = array_slice($fields, 0, 5);
        $parts = $this->viewParts($fields, $vars['singular']);
        $files[] = $this->write("resources/views/admin/{$route}/index.php", $this->render('admin/index', $vars + [
            'headers' => implode("\n", array_map(
                static fn (ResourceField $field): string => $field->type === 'text'
                    ? "                    <th class=\"px-4 py-3 font-medium\">{{ __('{$field->label()}') }}</th>"
                    : "                    <th class=\"px-4 py-3 font-medium\"><x-admin.sort-link :listado=\"\$listado\" column=\"{$field->name}\" :label=\"__('{$field->label()}')\" /></th>",
                $listed
            )),
            'cells' => implode("\n", array_map(
                static fn (ResourceField $field): string => "                    <td class=\"px-4 py-3\">{$field->display($vars['singular'])}</td>",
                $listed
            )),
            'colspan' => (string) (count($listed) + 2),
            'filterInputs' => implode("\n", array_map(static fn (ResourceField $field): string => self::filterInput($field), $filterable)),
        ]));
        // En el panel las etiquetas de los campos se traducen con __()
        $translated = static fn (string $html): string => (string) preg_replace('/ label="([^"]+)"/', " :label=\"__('$1')\"", $html);
        $files[] = $this->write("resources/views/admin/{$route}/create.php", $this->render('admin/create', $vars + ['fields' => $translated($parts['fields'])]));
        $files[] = $this->write("resources/views/admin/{$route}/edit.php", $this->render('admin/edit', $vars + ['editFields' => $translated($parts['editFields'])]));

        $files[] = $this->write("tests/Feature/Admin{$model}Test.php", $this->render('admin/test', $vars + [
            'data' => $this->testParts($fields, $vars + ['camel' => $vars['singular']])['data'],
            'seeIndex' => ($display = array_values(array_filter($fields, static fn (ResourceField $field): bool => ! $field->nullable && in_array($field->type, ['string', 'email'], true)))[0] ?? null) === null
                ? '' : "->assertSee(\${$vars['singular']}->{$display->name})",
        ]));

        // Una sola vez: layout, componente de orden y config/admin.php
        foreach ([
            'resources/views/components/layouts/admin.php' => 'admin/layout',
            'resources/views/components/admin/sort-link.php' => 'admin/sort-link',
            'config/admin.php' => 'admin/config',
        ] as $path => $stub) {
            if (! file_exists($this->basePath . '/' . $path)) {
                $files[] = $this->write($path, $this->render($stub, []));
            }
        }

        $files[] = $this->addAdminMenu($route, Str::headline($plural));
        $files[] = $this->addAdminRoutes($route, $model);

        return $files;
    }

    private static function filterInput(ResourceField $field): string
    {
        if ($field->type === 'bool') {
            return <<<HTML
                        <label class="flex flex-col gap-1 text-sm text-gray-600">
                            {{ __('{$field->label()}') }}
                            <select name="filtro[{$field->name}]" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
                                <option value="">{{ __('Todos') }}</option>
                                <option value="1" @if (\$listado->filter('{$field->name}') === '1') selected @endif>{{ __('Sí') }}</option>
                                <option value="0" @if (\$listado->filter('{$field->name}') === '0') selected @endif>{{ __('No') }}</option>
                            </select>
                        </label>
                HTML;
        }

        return <<<HTML
                    <label class="flex flex-col gap-1 text-sm text-gray-600">
                        {{ __('{$field->label()}') }} {{ __('desde') }}
                        <input type="date" name="filtro[{$field->name}_desde]" value="{{ \$listado->filter('{$field->name}_desde') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
                    </label>
                    <label class="flex flex-col gap-1 text-sm text-gray-600">
                        {{ __('hasta') }}
                        <input type="date" name="filtro[{$field->name}_hasta]" value="{{ \$listado->filter('{$field->name}_hasta') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-gray-900">
                    </label>
            HTML;
    }

    /**
     * Agrega el recurso al menú de config/admin.php.
     */
    private function addAdminMenu(string $route, string $label): string
    {
        $file = $this->basePath . '/config/admin.php';
        $contents = (string) file_get_contents($file);
        $marker = '        // make:admin agrega aquí cada recurso';
        $line = "        ['route' => 'admin.{$route}.index', 'label' => '{$label}'],";

        if (str_contains($contents, "'admin.{$route}.index'")) {
            return 'config/admin.php (ya estaba en el menú)';
        }

        if (! str_contains($contents, $marker)) {
            throw new RuntimeException("No se pudo editar config/admin.php. Agrega a mano en 'menu': {$line}");
        }

        file_put_contents($file, str_replace($marker, $line . "\n" . $marker, $contents));

        return 'config/admin.php (menú)';
    }

    /**
     * Agrega las rutas al grupo /admin de routes/web.php (lo crea si no existe).
     */
    private function addAdminRoutes(string $route, string $model): string
    {
        $file = $this->basePath . '/routes/web.php';
        $contents = is_file($file) ? (string) file_get_contents($file) : '';
        $lines = "        \$router->get('/{$route}/exportar', [Admin\\{$model}Controller::class, 'export'])->name('{$route}.export');\n"
            . "        \$router->resource('{$route}', Admin\\{$model}Controller::class, ['except' => ['show']]);";
        $group = "\$router->group(['prefix' => '/admin'";

        if (! str_contains($contents, $group)) {
            $closing = strrpos($contents, '};');

            if ($closing === false) {
                throw new RuntimeException("No se pudo editar routes/web.php. Agrega a mano un grupo /admin con:\n{$lines}");
            }

            $block = "\n    // Panel de administración (make:admin): solo admins con el correo verificado\n"
                . "    \$router->group(['prefix' => '/admin', 'as' => 'admin.', 'middleware' => ['auth', 'verified', 'role:admin']], static function (Router \$router): void {\n"
                . "    });\n";
            $contents = rtrim(substr($contents, 0, $closing)) . "\n" . $block . substr($contents, $closing);
        }

        $start = (int) strpos($contents, $group);
        $end = strpos($contents, "\n    });", $start);

        if ($end === false) {
            throw new RuntimeException("No se pudo editar el grupo /admin de routes/web.php. Agrega a mano:\n{$lines}");
        }

        $contents = substr($contents, 0, $end) . "\n" . $lines . substr($contents, $end);

        // use App\Controllers\Admin; junto a los demás, en orden
        $use = 'use App\\Controllers\\Admin;';
        preg_match_all('/^use [^;]+;$/m', $contents, $matches);
        if (! in_array($use, $matches[0], true)) {
            $sorted = [...$matches[0], $use];
            sort($sorted);
            $contents = $matches[0] === []
                ? (string) preg_replace('/^(declare\(strict_types=1\);\n)/m', "$1\n{$use}\n", $contents, 1)
                : str_replace(implode("\n", $matches[0]), implode("\n", $sorted), $contents);
        }

        file_put_contents($file, $contents);

        return 'routes/web.php (rutas /admin)';
    }

    /**
     * Partes de las vistas que dependen de los campos.
     *
     * @param list<ResourceField> $fields
     * @return array<string, string>
     */
    private function viewParts(array $fields, string $singular): array
    {
        $listed = array_slice($fields, 0, 4);
        $first = $fields[0];
        $cells = [];

        foreach ($listed as $field) {
            $value = $field->display($singular);
            $cells[] = $field === $first
                ? "                    <td class=\"px-4 py-3\"><a href=\"{{ route('[[route]].show', ['id' => \${$singular}->id]) }}\" class=\"font-medium text-blue-600 hover:underline\">{$value}</a></td>"
                : "                    <td class=\"px-4 py-3\">{$value}</td>";
        }

        return [
            'headers' => implode("\n", array_map(
                static fn (ResourceField $field): string => "                    <th class=\"px-4 py-3 font-medium\">{$field->label()}</th>",
                $listed
            )),
            'cells' => implode("\n", $cells),
            'colspan' => (string) (count($listed) + 1),
            'fields' => implode("\n", array_map(
                static fn (ResourceField $field): string => '            ' . $field->input(null),
                $fields
            )),
            'editFields' => implode("\n", array_map(
                static fn (ResourceField $field): string => '            ' . $field->input($singular),
                $fields
            )),
            'details' => implode("\n", array_map(
                static fn (ResourceField $field): string => "            <div class=\"grid grid-cols-3 gap-4 py-3\">\n"
                    . "                <dt class=\"text-sm font-medium text-gray-500\">{$field->label()}</dt>\n"
                    . "                <dd class=\"col-span-2 text-sm text-gray-900\">{$field->display($singular)}</dd>\n"
                    . '            </div>',
                $fields
            )),
        ];
    }

    /**
     * Partes del test generado.
     *
     * @param list<ResourceField> $fields
     * @param array<string, string> $vars
     * @return array<string, string>
     */
    private function testParts(array $fields, array $vars): array
    {
        $data = static fn (bool $updated): string => implode("\n", array_map(
            static fn (ResourceField $field): string => "        '{$field->name}' => '" . addslashes($field->sample($updated)) . "',",
            $fields
        ));
        $plain = array_values(array_filter($fields, static fn (ResourceField $field): bool => $field->isPlainText()));
        $required = array_values(array_filter($fields, static fn (ResourceField $field): bool => str_starts_with($field->rules(), 'required')));
        $has = static fn (bool $updated): string => $plain === [] ? '' : "\n        ->assertDatabaseHas('{$vars['table']}', ["
            . implode(', ', array_map(static fn (ResourceField $field): string => "'{$field->name}' => '" . addslashes($field->sample($updated)) . "'", $plain))
            . '])';
        $display = array_values(array_filter($fields, static fn (ResourceField $field): bool => ! $field->nullable && in_array($field->type, ['string', 'email'], true)))[0] ?? null;

        $validation = '';
        if ($required !== []) {
            $keys = implode(', ', array_map(static fn (ResourceField $field): string => "'{$field->name}'", $required));
            $validation = <<<PHP

                it('valida los datos antes de guardar', function (): void {
                    \$client = client();

                    \$client->from('/{$vars['route']}/create')->post('/{$vars['route']}', [])
                        ->assertRedirect('/{$vars['route']}/create')
                        ->assertSessionHasErrors([{$keys}]);

                    \$client->assertDatabaseCount('{$vars['table']}', 0);
                });

                PHP;
        }

        return [
            'data' => $data(false),
            'updatedData' => $data(true),
            'seeIndex' => $display === null ? '' : "->assertSee(\${$vars['singular']}->{$display->name})",
            'seeEdit' => $display === null ? '' : "->assertSee(\${$vars['singular']}->{$display->name})",
            'hasCreated' => $has(false),
            'hasUpdated' => $plain === [] ? '' : "\n    \$client" . str_replace("\n        ", "\n        ", $has(true)) . ";\n",
            'validationTest' => $validation,
        ];
    }

    /**
     * @param list<ResourceField> $fields
     */
    private function createTableMigration(string $table, array $fields, ?string $name = null): string
    {
        $mysql = $this->driver === 'mysql';
        $columns = [
            $mysql ? 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            ...array_map(fn (ResourceField $field): string => $field->column($this->driver), $fields),
            $mysql ? 'created_at DATETIME NULL' : 'created_at TEXT',
            $mysql ? 'updated_at DATETIME NULL' : 'updated_at TEXT',
        ];

        return $this->write($this->migrationPath($name ?? 'crear_' . $table), $this->render('migration.create', [
            'table' => $table,
            'columns' => implode(",\n", array_map(static fn (string $column): string => '                ' . $column, $columns)),
        ]));
    }

    /**
     * @param list<ResourceField> $fields
     */
    private function renderModel(string $class, string $table, array $fields, bool $factory = false): string
    {
        $casts = array_filter(array_map(static fn (ResourceField $field): ?string => $field->cast() === null ? null : "'{$field->name}' => '{$field->cast()}'", $fields));

        return $this->render('model', [
            'Class' => $class,
            'Description' => "Modelo de la tabla {$table}.",
            'table' => $table,
            'properties' => implode('', array_map(static fn (ResourceField $field): string => " * @property {$field->phpType()} \${$field->name}\n", $fields)),
            'fillable' => implode(', ', array_map(static fn (ResourceField $field): string => "'{$field->name}'", $fields)),
            'casts' => $casts === [] ? '' : "\n    protected static array \$casts = [" . implode(', ', $casts) . "];\n",
            // Así PHPStan conoce los métodos propios de la factory (estados como ->hechas())
            'factory' => $factory ? " * @method static \\Database\\Factories\\{$class}Factory factory()\n" : '',
        ]);
    }

    /**
     * Agrega $router->resource(...) y el use del controlador a routes/web.php.
     */
    private function addResourceRoute(string $route, string $controller): string
    {
        $file = $this->basePath . '/routes/web.php';
        $contents = is_file($file) ? (string) file_get_contents($file) : '';
        $closing = strrpos($contents, '};');

        if ($closing === false) {
            throw new RuntimeException("No se pudo editar routes/web.php. Agrega a mano: \$router->resource('{$route}', {$controller}::class);");
        }

        // Dentro de $router->localized() si existe, así el CRUD también responde en /en/...
        $opening = strpos($contents, '$router->localized(');
        $localizedEnd = $opening === false ? false : strpos($contents, "\n    });", $opening);

        if ($localizedEnd !== false) {
            $line = "        \$router->resource('{$route}', {$controller}::class);";
            $contents = substr($contents, 0, $localizedEnd) . "\n\n" . $line . substr($contents, $localizedEnd);
        } else {
            $line = "    \$router->resource('{$route}', {$controller}::class);\n";
            $contents = substr($contents, 0, $closing) . "\n" . $line . substr($contents, $closing);
        }

        $contents = (string) preg_replace("/\n\n\n+( +\\\$router->resource)/", "\n\n$1", $contents);

        // Inserta el use en orden alfabético junto a los demás
        $use = "use App\\Controllers\\{$controller};";
        preg_match_all('/^use [^;]+;$/m', $contents, $matches);
        $uses = $matches[0];

        if ($uses === []) {
            $contents = (string) preg_replace('/^(declare\(strict_types=1\);\n)/m', "$1\n{$use}\n", $contents, 1);
        } elseif (! in_array($use, $uses, true)) {
            $sorted = [...$uses, $use];
            sort($sorted);
            $block = implode("\n", $uses);
            $contents = str_replace($block, implode("\n", $sorted), $contents);
        }

        file_put_contents($file, $contents);

        return 'routes/web.php (ruta agregada)';
    }

    private function migrationPath(string $name): string
    {
        $time = time();

        // Siempre después de la última migración existente, aunque se creen varias en el mismo segundo
        foreach (glob($this->basePath . '/database/migrations/*.php') ?: [] as $existing) {
            $date = \DateTimeImmutable::createFromFormat('Y_m_d_His', substr(basename($existing), 0, 17));

            if ($date !== false && $date->getTimestamp() >= $time) {
                $time = $date->getTimestamp() + 1;
            }
        }

        return 'database/migrations/' . date('Y_m_d_His', $time) . "_{$name}.php";
    }

    private function tableName(string $class, ?string $table): string
    {
        $table ??= Str::snake(Str::plural($class));

        if (preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1) {
            throw new InvalidArgumentException("Nombre de tabla inválido [{$table}]: usa minúsculas y guion bajo.");
        }

        return $table;
    }

    /**
     * 'task', 'order_item' u 'OrderItem' => 'OrderItem' (con sufijo si falta).
     */
    private static function className(string $name, string $suffix = ''): string
    {
        $class = Str::studly($name);

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $class) !== 1) {
            throw new InvalidArgumentException("Nombre inválido [{$name}]: usa letras y números, por ejemplo Tarea u OrderItem.");
        }

        return $suffix !== '' && ! str_ends_with($class, $suffix) ? $class . $suffix : $class;
    }

    /**
     * Rellena una plantilla de framework/stubs.
     *
     * @param array<string, string> $vars
     */
    private function render(string $stub, array $vars): string
    {
        $path = $this->stubs . '/' . $stub . '.stub';
        $template = is_file($path) ? file_get_contents($path) : false;

        if ($template === false) {
            throw new RuntimeException("No se encontró la plantilla {$path}.");
        }

        $pairs = [];
        foreach ($vars as $key => $value) {
            $pairs['[[' . $key . ']]'] = $value;
        }

        // Dos pasadas: algunas partes generadas contienen marcadores (por ejemplo [[route]])
        return strtr(strtr($template, $pairs), $pairs);
    }

    /**
     * Escribe un archivo nuevo y devuelve su ruta relativa.
     *
     * @throws RuntimeException Si ya existe
     */
    private function write(string $relative, string $contents): string
    {
        $path = $this->basePath . '/' . $relative;

        if (file_exists($path)) {
            throw new RuntimeException("Ya existe {$relative}. Bórralo o elige otro nombre.");
        }

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('No se pudo crear la carpeta ' . dirname($relative) . '.');
        }

        file_put_contents($path, $contents);

        return $relative;
    }
}
