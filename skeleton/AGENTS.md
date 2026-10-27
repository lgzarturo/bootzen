# AGENTS.md

Guía para agentes de IA (y personas) que trabajan en **__APP_NAME__**, una
aplicación monolítica construida con BootZen.

## Stack

- PHP 8.2+ con `declare(strict_types=1)` en todos los archivos.
- Framework BootZen en `framework/` (código fuente, namespace `BootZen\`).
- Código de la aplicación en `app/` (namespace `App\`, PSR-4).
- Sin dependencias de Composer en producción. Composer solo instala
  herramientas de desarrollo: Pest, PHPStan (nivel 8) y PHP CS Fixer.

## Mapa del proyecto

| Ruta                       | Contenido                                                     |
| -------------------------- | ------------------------------------------------------------- |
| `app/Controllers/`         | Controladores (`App\Controllers`)                             |
| `bootstrap/app.php`        | Construye la `Application`: middleware (alias, grupos) y rutas |
| `config/`                  | Archivos PHP que retornan arrays: `config('app.name')`        |
| `.env`                     | Variables locales (fuera de git). Plantilla: `.env.example`   |
| `routes/web.php`           | Rutas; retorna un closure que recibe el `Router`              |
| `resources/views/`         | Vistas `.php` con directivas y componentes `<x-*>`            |
| `resources/views/components/` | Componentes: `card.php` es `<x-card>`, `layouts/app.php` es `<x-layouts.app>` |
| `resources/views/errors/`  | Páginas de error: `{codigo}.php` o `default.php`              |
| `resources/css/app.css`    | Fuente de Tailwind CSS v4                                     |
| `app/View/Components/`     | Clases opcionales de componentes con lógica                   |
| `public/`                  | Document root: `index.php`, `.htaccess` y `assets/`           |
| `public/assets/app.css`    | CSS compilado (se genera, no se edita a mano)                 |
| `public/assets/js/`        | JS nativo: `app.js`, `components/*.js` y `bootzen.js` (runtime) |
| `bin/bootzen`              | CLI del proyecto                                              |
| `storage/`                 | Logs (`storage/logs`) y cachés (escribible, fuera de git)     |
| `app/Models/`              | Modelos (`App\Models`), uno por tabla                         |
| `config/database.php`      | Conexión: SQLite por defecto, MySQL opcional                  |
| `database/migrations/`     | Migraciones con SQL directo, en orden de nombre de archivo    |
| `database/seeds/`          | Datos de ejemplo: `DatabaseSeeder.php` y los que llame        |
| `database/app.sqlite`      | Base SQLite local (fuera de git)                              |
| `tests/`                   | Tests con Pest (`Feature/` y `Unit/`)                         |
| `framework/`               | BootZen. **No editar**: se actualiza desde el repo oficial    |

## Comandos

```bash
php bin/bootzen serve             # servidor + Tailwind en modo watch (http://127.0.0.1:8000)
php bin/bootzen css:build         # compila el CSS (necesario antes de desplegar)
php bin/bootzen view:cache        # precompila las vistas (producción)
php bin/bootzen view:clear        # borra las vistas compiladas
php bin/bootzen migrate           # ejecuta las migraciones pendientes
php bin/bootzen migrate:status    # qué migraciones corrieron
php bin/bootzen migrate:rollback  # revierte el último lote (--step=N)
php bin/bootzen db:seed           # datos de ejemplo (--class=Nombre)
php bin/bootzen db:fresh --seed   # borra todo, migra y siembra (--force en producción)
php bin/bootzen db:backup         # copia consistente de SQLite en storage/backups
composer install                  # solo la primera vez, instala herramientas de dev
composer check                    # formato + PHPStan + tests. Debe pasar siempre
composer format                   # corrige el formato
composer test                     # solo tests
```

La primera vez que se compila el CSS, `bin/bootzen` descarga el binario de
Tailwind en `storage/bin/` y verifica su SHA-256. No hace falta Node.

**Antes de dar una tarea por terminada, ejecuta `composer check` y deja todo
en verde.**

## Cómo se hacen las cosas

### Rutas

```php
// routes/web.php
return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index'])->name('home');

    // CRUD completo con nombres tareas.index, tareas.create, tareas.store,
    // tareas.show, tareas.edit, tareas.update y tareas.destroy
    $router->resource('tareas', TareaController::class);

    // Restricción en línea: solo números
    $router->get('/facturas/{id:\d+}', [FacturaController::class, 'show'])->name('facturas.show');

    $router->group(['prefix' => '/admin', 'as' => 'admin.', 'middleware' => 'auth'], function (Router $router): void {
        $router->get('/', [AdminController::class, 'index'])->name('index'); // admin.index
    });

    $router->group(['prefix' => '/api', 'as' => 'api.', 'middleware' => 'api'], function (Router $router): void {
        $router->get('/tareas', [TareaApiController::class, 'index'])->name('tareas.index');
    });
};
```

- Usa siempre `[Controlador::class, 'metodo']`. Los closures solo para
  respuestas triviales (como `/up`). El formato `'Clase@metodo'` no existe.
- **Ponle nombre a cada ruta** y genera URLs con `route('tareas.show', ['id' => 5])`
  (devuelve el path `/tareas/5`; los parámetros sobrantes van al query string).
- Las rutas de `resource()` usan `{id}` como parámetro.
- Si la ruta existe con otro método, el router responde 405 solo; si no
  existe, 404. No hace falta programarlo.
- Los formularios HTML envían PUT, PATCH o DELETE con un POST y el campo
  oculto `_method`.

### Controladores

```php
final class TareaController
{
    public function __construct(private readonly TareaService $tareas)
    {
    }

    public function show(int $id): Response
    {
        $tarea = $this->tareas->find($id) ?? abort(404, 'Tarea no encontrada');

        return view('tareas.show', ['tarea' => $tarea]);
    }
}
```

- Una clase por recurso en `app/Controllers/`.
- Los parámetros de ruta se inyectan **por nombre** (`{id}` llega a
  `int $id`, convertido al tipo declarado). El `Request` y cualquier clase se
  inyectan por tipo; el orden no importa.
- Las dependencias van por constructor (el contenedor las resuelve solo).
- Para HTML retorna `view('nombre', [...])`. Retornar un `array` responde
  JSON; un `string`, HTML; `null`, 204.

### Vistas

`view('tareas.index', ['tareas' => $tareas])` renderiza
`resources/views/tareas/index.php`. Las vistas son PHP con una **lista cerrada**
de extensiones; cualquier otra cosa se escribe en PHP plano:

| Sintaxis                                   | Uso                                              |
| ------------------------------------------ | ------------------------------------------------ |
| `{{ $valor }}`                             | Imprime escapado (úsalo siempre)                 |
| `{!! $html !!}`                            | Imprime sin escapar (solo HTML confiable)        |
| `{{-- comentario --}}`                     | Comentario que no llega al HTML                  |
| `@{{ texto }}`                             | Imprime `{{ texto }}` literal (para JS)          |
| `@if (...)` `@elseif (...)` `@else` `@endif` | Condicionales                                  |
| `@foreach (...)` `@empty` `@endforeach`    | Bucles, con rama para lista vacía                |
| `@csrf` `@method('PUT')`                   | Campos ocultos de formulario                     |
| `@error('campo')` ... `@enderror`          | Mensaje de validación en `$message`              |
| `@props([...])`                            | Props de un componente                           |
| `<x-nombre attr="texto" :attr="$expr">`    | Componente                                       |
| `<x-slot:nombre>` ... `</x-slot:nombre>`   | Slot con nombre                                  |

- Una directiva no puede ir pegada a una letra o número (`texto@if` no es
  directiva, así los correos no se confunden); pon un espacio o salto antes.
- Para clases condicionales usa `class="{{ class_list(['px-2', 'font-bold' => $activo]) }}"`.
- Las vistas se compilan solas a `storage/cache/views` cuando cambian.
- Los errores en una vista indican el archivo y la línea de la vista original.

### Componentes

Un componente es una vista en `resources/views/components/`:

```php
{{-- resources/views/components/badge.php --}}
@props(['color' => 'gray'])
<span {{ $attributes->class(['rounded px-2 text-xs', 'bg-green-100' => $color === 'green']) }}>
    {{ $slot }}
</span>
```

```php
<x-badge color="green" title="Activo">Activa</x-badge>
<x-card :title="$tarea->titulo">
    {{ $tarea->descripcion }}
    <x-slot:footer><x-button :href="route('tareas.edit', ['id' => $tarea->id])">Editar</x-button></x-slot:footer>
</x-card>
```

- `@props` declara las props (con valor por defecto o, si solo es el nombre,
  obligatorias). El resto de atributos queda en `$attributes`.
- `attr="texto"` pasa un string (admite `{{ }}` dentro); `:attr="$expr"` pasa
  una expresión PHP; `attr` solo pasa `true`.
- `{{ $slot }}` es el contenido; los slots con nombre llegan como variables.
- Si un componente necesita lógica, crea `app/View/Components/Badge.php`
  (`extends BootZen\View\Component`): su constructor recibe los atributos por
  nombre y sus propiedades públicas y `data()` llegan a la vista.
- Disponibles: `<x-layouts.app>`, `<x-navigation>`, `<x-card>`, `<x-button>`,
  `<x-alert>`, `<x-form>`, `<x-input>`. Úsalos antes de crear otros.

### CSS (Tailwind v4)

- Usa clases de Tailwind directamente en las vistas y en `public/assets/js`.
- `php bin/bootzen serve` recompila el CSS al guardar; antes de desplegar,
  `php bin/bootzen css:build`.
- Estilos propios o configuración de Tailwind: `resources/css/app.css`.

### JavaScript

JS nativo con módulos ES, sin bundler ni `node_modules`. Para interactividad
crea un componente en `public/assets/js/components/<nombre>.js`:

```js
// public/assets/js/components/contador.js
export default function contador(element, { inicio = 0 }) {
  let valor = inicio;
  element.querySelector('button').addEventListener('click', () => {
    element.querySelector('output').textContent = ++valor;
  });
}
```

```html
<div data-component="contador" data-props='{"inicio": 3}'>
  <button>Sumar</button> <output>3</output>
</div>
```

- `bootzen.js` es el runtime (no lo edites). Exporta `request()` (fetch con
  JSON y CSRF), `toast()` y `mount()`: `import { request } from '../bootzen.js';`
- Atributos listos: `data-confirm="¿Seguro?"`, `data-remote` (envía un
  formulario con fetch), `data-no-disable`, `data-dismiss` dentro de
  `data-dismissible`.
- Nada de `<script>` con lógica dentro de las vistas: va en un componente.

### Base de datos

**Migraciones.** Un archivo por cambio en `database/migrations/`, con nombre
`AAAA_MM_DD_HHMMSS_descripcion.php` (el orden de ejecución es el del nombre).
Retornan una clase anónima con **SQL directo**:

```php
<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\Migration;

return new class () extends Migration {
    public function up(Connection $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE tareas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                proyecto_id INTEGER REFERENCES proyectos(id) ON DELETE CASCADE,
                titulo TEXT NOT NULL,
                hecha INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            )
            SQL);
    }

    public function down(Connection $db): void
    {
        $db->exec('DROP TABLE tareas');
    }
};
```

- Nunca edites una migración que ya corrió: crea otra nueva.
- Las tablas de modelos con timestamps llevan `created_at` y `updated_at`.
- SQL de SQLite por defecto (`INTEGER PRIMARY KEY AUTOINCREMENT`, `TEXT`). Si
  `DB_CONNECTION=mysql`, escribe SQL de MySQL (`INT AUTO_INCREMENT PRIMARY KEY`).

**Modelos.** Uno por tabla en `app/Models/`, con `@property` para que PHPStan
conozca las columnas:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use BootZen\Database\Model;
use BootZen\Database\ModelQuery;

/**
 * @property int $id
 * @property int|null $proyecto_id
 * @property string $titulo
 * @property bool $hecha
 * @property \DateTimeImmutable|null $created_at
 */
final class Tarea extends Model
{
    protected static string $table = 'tareas';          // obligatoria
    protected static array $fillable = ['titulo', 'hecha', 'proyecto_id'];
    protected static array $casts = ['hecha' => 'bool'];  // int, float, bool, string, array, datetime, date
    protected static array $hidden = [];                  // columnas que no salen en JSON

    public function proyecto(): ?Proyecto
    {
        return $this->belongsTo(Proyecto::class);         // usa proyecto_id
    }
}
```

```php
$tarea = Tarea::create($request->only(['titulo']));   // solo asigna $fillable
$tarea = Tarea::findOrFail($id);                       // 404 automático si no existe
$tarea->update(['hecha' => true]);
$tarea->delete();
$pendientes = Tarea::where('hecha', false)->latest()->get();
$tareas = Tarea::query()->orderBy('titulo')->paginate(15, $request->integer('page', 1));
$proyecto->tareas()->count();                          // hasMany(Tarea::class) en Proyecto
```

- En vistas, `<x-pagination :paginator="$tareas" />` dibuja los enlaces.
- Devolver un modelo o un paginador desde un controlador responde JSON.
- No hay carga anticipada de relaciones: en listados grandes, evita llamar
  una relación por cada fila; usa `whereIn` con los ids o un `join`.

**Query builder.** Para consultas que no son de un modelo:

```php
db()->table('tareas')->select('id', 'titulo')->where('hecha', false)->orderBy('id', 'desc')->limit(10)->get();
db()->table('tareas')->join('proyectos', 'proyectos.id', '=', 'tareas.proyecto_id')->count();
db()->table('tareas')->whereRaw('lower(titulo) like ?', ['%pan%'])->get();
db()->transaction(function ($db): void { /* varias escrituras */ });
```

- Los valores siempre van como parámetros; para expresiones usa `selectRaw`
  y `whereRaw` con `?`.
- `update()` y `delete()` sin `where` fallan a propósito.

**Seeders.** `database/seeds/DatabaseSeeder.php` retorna una clase anónima
que extiende `BootZen\Database\Seeder`; `$this->call('Otro')` ejecuta
`database/seeds/Otro.php`.

### Request

```php
$request->input('nombre');            // cuerpo o query string
$request->input('items.0.precio');    // notación de puntos
$request->only(['nombre', 'email']);
$request->except(['password']);
$request->has('campo');               // presente aunque esté vacío
$request->filled('campo');            // presente y no vacío
$request->boolean('activo');          // '1', 'on', 'true', 'yes'
$request->integer('pagina', 1);
$request->param('id');                // parámetro de ruta
$request->wantsJson();                // el cliente pide JSON
```

### Respuestas y errores

```php
return Response::html($html);
return Response::json(['data' => $items], 201);
return Response::noContent();
return redirect(route('tareas.index')); // retorna un Response: siempre con return
abort(404, 'Tarea no encontrada');      // lanza HttpException, corta la petición
abort(403);
```

- Los errores se muestran con `resources/views/errors/{codigo}.php` o
  `errors/default.php` (variables `$status`, `$title`, `$message`). Si el
  cliente pide JSON, la respuesta es JSON automáticamente.
- Con `APP_DEBUG=true` los errores 500 muestran una página de depuración con
  traza y código. Nunca actives `APP_DEBUG` en producción.
- Las excepciones no controladas se registran en `storage/logs/`.

### Configuración y entorno

- Valores por entorno en `.env` (nunca en git); se leen en `config/*.php` con
  `env('CLAVE', 'por defecto')`.
- En el código de la aplicación usa `config('archivo.clave')`, no `env()`.
- Para una configuración nueva, crea `config/{nombre}.php` que retorne un
  array y agrega sus variables a `.env.example`.

### Log y caché

```php
logger('Pedido {id} creado', 'info', ['id' => $id]);
logger('Pago rechazado', 'error', ['orden' => $orden]);

$stats = cache()->remember('dashboard.stats', 600, fn () => $this->calcularStats());
cache()->forget('dashboard.stats');
```

### Middleware

Implementa `BootZen\Core\MiddlewareInterface`:

```php
public function process(Request $request, callable $next): Response
{
    // antes
    $response = $next($request);
    // después
    return $response;
}
```

Regístralo en `bootstrap/app.php`:

- Global: `$app->addGlobalMiddleware(MiMiddleware::class)`.
- Alias para rutas: `$app->aliasMiddleware('auth', AuthMiddleware::class)`.
- Grupo: `$app->middlewareGroup('api', ['cors'])`.

En rutas: `->middleware('auth')` o `'middleware' => 'auth'` en un `group`.
Ya existen el alias `cors` y el grupo `api`.

### Tests

Cada funcionalidad nueva lleva al menos un test de feature. `createApp()`
usa una base SQLite en memoria con todas las migraciones aplicadas, vacía en
cada llamada:

```php
it('lista las tareas', function (): void {
    $response = createApp()->run(new Request('GET', '/tareas'));

    expect($response->getStatusCode())->toBe(200);
});

it('crea una tarea', function (): void {
    $response = createApp()->run(new Request('POST', '/tareas', [], ['titulo' => 'Comprar pan']));

    expect($response->getStatusCode())->toBe(302)
        ->and(Tarea::where('titulo', 'Comprar pan')->exists())->toBeTrue();
});
```

## API disponible del framework

- **Router**: `get`, `post`, `put`, `patch`, `delete`, `options`, `match`,
  `any`, `resource`, `group(['prefix', 'as', 'middleware'], fn)`; en la ruta:
  `->name()`, `->middleware()`, `->where()`.
- **Request**: `input`, `all`, `only`, `except`, `has`, `filled`, `boolean`,
  `integer`, `param`, `params`, `getRoute`, `getMethod`, `getPath`,
  `getQuery`, `getBody`, `getHeader`, `getFile`, `getCookie`, `wantsJson`,
  `isJson`, `isAjax`, `isSecure`, `getClientIp`.
- **Response**: `html`, `json`, `redirect`, `noContent`, `withHeader`,
  `withHeaders`, `withCookie`, `withStatus`.
- **Vistas**: `view()`, `class_list()`; en componentes `$attributes` con
  `merge`, `class`, `only`, `except`, `has`, `get`.
- **Model**: `query`, `all`, `find`, `findOrFail`, `where`, `create`, `fill`,
  `save`, `update`, `delete`, `refresh`, `toArray`, `getKey`, `isDirty`,
  `hasMany`, `belongsTo`.
- **Consultas** (QueryBuilder y ModelQuery): `select`, `selectRaw`, `where`,
  `orWhere`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `whereRaw`,
  `join`, `leftJoin`, `orderBy`, `latest`, `limit`, `offset`, `get`, `first`,
  `firstOrFail`, `find`, `value`, `pluck`, `count`, `exists`, `paginate`,
  `insert`, `update`, `delete`.
- **Helpers**: `db()`, `config()`, `env()`, `route()`, `url()`, `asset()`, `app()`,
  `logger()`, `cache()`, `base_path()`, `public_path()`, `storage_path()`,
  `e()`, `redirect()`, `abort()`, `json_response()`, `download()`,
  `csrf_token()`, `csrf_field()`, `validate_csrf_token()`, `old()`,
  `slugify()`, `str_random()`.

## Lo que todavía NO existe

BootZen está en desarrollo hacia la v2.0. Estas piezas llegan en fases
posteriores; **no las inventes ni instales librerías para suplirlas** sin
preguntar:

- Sesiones, mensajes flash, validación y autenticación (`@error` y `<x-input>`
  ya muestran errores, pero nada los llena todavía).
- Generadores en `bin/bootzen` (`make:model`, `make:migration`, `make:resource`)
  y `config:cache`: por ahora los archivos se crean a mano siguiendo esta guía.
- Factories de datos falsos para tests.

## Reglas

- No agregues dependencias de Composer en `require`. Solo `require-dev`.
- No edites `framework/`. Si algo del framework falla, repórtalo.
- No pongas HTML dentro de controladores: va en `resources/views/` con `view()`.
- Imprime con `{{ }}` (escapa); `{!! !!}` solo para HTML que tú generaste.
- No edites `public/assets/app.css` ni `public/assets/js/bootzen.js`.
- Nunca concatenes datos del usuario en SQL: usa parámetros (`?`).
- No uses PDO directo: `db()`, el query builder o los modelos.
- Nada de `exit` ni `die`: retorna un `Response` o usa `abort()`.
- No leas `env()` fuera de `config/`.
