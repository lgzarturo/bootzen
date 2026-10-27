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
| `resources/views/`         | Vistas `.php`                                                 |
| `resources/views/errors/`  | Páginas de error: `{codigo}.php` o `default.php`              |
| `public/`                  | Document root. Solo `index.php`, `.htaccess` y assets         |
| `storage/`                 | Logs (`storage/logs`) y cachés (escribible, fuera de git)     |
| `database/`                | Base de datos SQLite local (fuera de git)                     |
| `tests/`                   | Tests con Pest (`Feature/` y `Unit/`)                         |
| `framework/`               | BootZen. **No editar**: se actualiza desde el repo oficial    |

## Comandos

```bash
php -S localhost:8000 -t public   # servidor de desarrollo (o: composer serve)
composer install                  # solo la primera vez, instala herramientas de dev
composer check                    # formato + PHPStan + tests. Debe pasar siempre
composer format                   # corrige el formato
composer test                     # solo tests
```

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

    public function show(Request $request, int $id): Response
    {
        $tarea = $this->tareas->find($id) ?? abort(404, 'Tarea no encontrada');

        return Response::json($tarea);
    }
}
```

- Una clase por recurso en `app/Controllers/`.
- Los parámetros de ruta se inyectan **por nombre** (`{id}` llega a
  `int $id`, convertido al tipo declarado). El `Request` y cualquier clase se
  inyectan por tipo; el orden no importa.
- Las dependencias van por constructor (el contenedor las resuelve solo).
- Retornar un `array` responde JSON; un `string`, HTML; `null`, 204.

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

Cada funcionalidad nueva lleva al menos un test de feature:

```php
it('lista las tareas', function (): void {
    $response = createApp()->run(new Request('GET', '/tareas'));

    expect($response->getStatusCode())->toBe(200);
});

it('crea una tarea', function (): void {
    $response = createApp()->run(new Request('POST', '/tareas', [], ['titulo' => 'Comprar pan']));

    expect($response->getStatusCode())->toBe(302);
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
- **Helpers**: `config()`, `env()`, `route()`, `url()`, `asset()`, `app()`,
  `logger()`, `cache()`, `base_path()`, `public_path()`, `storage_path()`,
  `e()`, `redirect()`, `abort()`, `json_response()`, `download()`,
  `csrf_token()`, `csrf_field()`, `validate_csrf_token()`, `old()`,
  `slugify()`, `str_random()`.

## Lo que todavía NO existe

BootZen está en desarrollo hacia la v2.0. Estas piezas llegan en fases
posteriores; **no las inventes ni instales librerías para suplirlas** sin
preguntar:

- Motor de vistas con componentes `<x-*>` (por ahora: vistas `.php` incluidas
  con `ob_start()`, ver `HomeController`).
- Base de datos (Connection, QueryBuilder, Model, migraciones).
- Sesiones, mensajes flash, validación y autenticación.
- CLI `bin/bootzen` (generadores, migraciones, cachés).
- Tailwind y JavaScript del lado del cliente.

## Reglas

- No agregues dependencias de Composer en `require`. Solo `require-dev`.
- No edites `framework/`. Si algo del framework falla, repórtalo.
- No pongas HTML largo dentro de controladores: va en `resources/views/`.
- Escapa toda salida con `e()`.
- Nunca concatenes datos del usuario en SQL.
- Nada de `exit` ni `die`: retorna un `Response` o usa `abort()`.
- No leas `env()` fuera de `config/`.
