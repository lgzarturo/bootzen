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

| Ruta                    | Contenido                                                     |
| ----------------------- | ------------------------------------------------------------- |
| `app/Controllers/`      | Controladores (`App\Controllers`)                             |
| `bootstrap/app.php`     | Construye la `Application`: middleware global y rutas         |
| `routes/web.php`        | Rutas; retorna un closure que recibe el `Router`              |
| `resources/views/`      | Vistas `.php`                                                 |
| `public/`               | Document root. Solo `index.php`, `.htaccess` y assets         |
| `storage/`              | Logs y cachés (escribible, fuera de git)                      |
| `database/`             | Base de datos SQLite local (fuera de git)                     |
| `tests/`                | Tests con Pest (`Feature/` y `Unit/`)                         |
| `framework/`            | BootZen. **No editar**: se actualiza desde el repo oficial    |

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
    $router->get('/tareas', [TareaController::class, 'index']);
    $router->post('/tareas', [TareaController::class, 'store']);
    $router->get('/tareas/{id}', [TareaController::class, 'show']);

    $router->group(['prefix' => '/admin', 'middleware' => [AdminMiddleware::class]], function (Router $router): void {
        $router->get('/', [AdminController::class, 'index']);
    });
};
```

- Usa siempre `[Controlador::class, 'metodo']`. Los closures solo para
  respuestas triviales (como `/up`).
- Los parámetros de ruta llegan como argumentos después del `Request`:
  `public function show(Request $request, string $id): Response`.

### Controladores

- Una clase por recurso en `app/Controllers/`, métodos públicos que reciben
  `Request` y retornan `Response`.
- Retornar un `array` responde JSON; retornar un `string` responde HTML.
- Las dependencias se inyectan por constructor (el contenedor las resuelve).

### Respuestas y errores

```php
return Response::html($html);
return Response::json(['data' => $items], 201);
return redirect('/tareas');          // retorna un Response: siempre con return
abort(404, 'Tarea no encontrada');   // lanza HttpException, corta la petición
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

Global en `bootstrap/app.php` con `$app->addGlobalMiddleware()`, o por ruta
con `->middleware()` o en un `group`.

### Tests

Cada funcionalidad nueva lleva al menos un test de feature:

```php
it('lista las tareas', function (): void {
    $response = createApp()->run(new Request('GET', '/tareas'));

    expect($response->getStatusCode())->toBe(200);
});
```

## API disponible del framework

- **Router**: `get`, `post`, `put`, `patch`, `delete`, `match`, `any`,
  `group(['prefix' => ..., 'middleware' => [...]], fn)`.
- **Request**: `getMethod()`, `getPath()`, `getQuery($key)`,
  `getBody($key)`, `getHeader($name)`, `getFile($key)`, `getCookie($name)`,
  `isJson()`, `isAjax()`, `isSecure()`, `getClientIp()`.
- **Response**: `html()`, `json()`, `redirect()`, `notFound()`,
  `badRequest()`, `unauthorized()`, `forbidden()`, `withHeader()`,
  `withCookie()`, `withStatus()`.
- **Helpers**: `env()`, `base_path()`, `public_path()`, `storage_path()`,
  `url()`, `asset()`, `e()`, `redirect()`, `abort()`, `json_response()`,
  `download()`, `logger()`, `csrf_token()`, `csrf_field()`,
  `validate_csrf_token()`, `old()`, `slugify()`, `str_random()`.

## Lo que todavía NO existe

BootZen está en desarrollo hacia la v2.0. Estas piezas llegan en fases
posteriores; **no las inventes ni instales librerías para suplirlas** sin
preguntar:

- Carga de `.env` y archivos de `config/` (por ahora: variables de entorno del
  sistema vía `env()`).
- Motor de vistas con componentes `<x-*>` (por ahora: vistas `.php` incluidas
  con `ob_start()`, ver `HomeController`).
- Base de datos (Connection, QueryBuilder, Model, migraciones).
- Sesiones, validación, autenticación y CLI `bin/bootzen`.
- Tailwind y JavaScript del lado del cliente.

## Reglas

- No agregues dependencias de Composer en `require`. Solo `require-dev`.
- No edites `framework/`. Si algo del framework falla, repórtalo.
- No pongas HTML largo dentro de controladores: va en `resources/views/`.
- Escapa toda salida con `e()`.
- Nunca concatenes datos del usuario en SQL.
- Nada de `exit` ni `die`: retorna un `Response` o usa `abort()`.
