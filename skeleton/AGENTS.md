# AGENTS.md

Guía para agentes de IA (y personas) que trabajan en **__APP_NAME__**, una
aplicación monolítica construida con BootZen.

## Stack

- PHP 8.2+ con `declare(strict_types=1)` en todos los archivos.
- Framework BootZen en `framework/` (código fuente, namespace `BootZen\`).
- Código de la aplicación en `app/` (namespace `App\`, PSR-4).
- Sin dependencias de Composer en producción. Composer solo instala
  herramientas de desarrollo: Pest, PHPStan (nivel 8) y PHP CS Fixer.

## Dónde buscar

- **Recetas** en `docs/ai/recipes/`: crear un CRUD, proteger rutas, endpoint
  JSON, componente (con un modal), migraciones, subir archivos, enviar
  correos, cola, tareas programadas, idiomas y panel de administración.
  Paso a paso, con tests.
  Úsalas antes de improvisar.
- **Referencia** en `docs/ai/reference.md`: todas las firmas públicas de
  BootZen, generadas desde el código. Si dudas de un método, búscalo ahí.
- **Skills** en `.claude/skills/` (y `.agents/skills/`): `crear-recurso`,
  `crear-componente` y `revisar-seguridad`.
- **Despliegue** en `docs/deploy/`: hosting compartido, VPS y Docker.

## Mapa del proyecto

| Ruta                       | Contenido                                                     |
| -------------------------- | ------------------------------------------------------------- |
| `app/Controllers/`         | Controladores (`App\Controllers`)                             |
| `bootstrap/app.php`        | Construye la `Application`: middleware (alias, grupos) y rutas |
| `config/`                  | Archivos PHP que retornan arrays: `config('app.name')`        |
| `.env`                     | Variables locales (fuera de git). Plantilla: `.env.example`   |
| `routes/web.php`           | Páginas (grupo `web`: sesión y CSRF); retorna un closure      |
| `routes/api.php`           | API JSON con prefijo `/api` (grupo `api`: CORS y límite)      |
| `resources/views/`         | Vistas `.php` con directivas y componentes `<x-*>`            |
| `resources/views/components/` | Componentes: `card.php` es `<x-card>`, `layouts/app.php` es `<x-layouts.app>` |
| `resources/views/errors/`  | Páginas de error: `{codigo}.php` o `default.php`              |
| `lang/`                    | Traducciones: `en.php` (y `en/*.php`) con texto en español => traducción; `en/rutas.php` traduce segmentos de URL |
| `resources/css/app.css`    | Fuente de Tailwind CSS v4                                     |
| `app/View/Components/`     | Clases opcionales de componentes con lógica                   |
| `public/`                  | Document root: `index.php`, `.htaccess` y `assets/`           |
| `public/assets/app.css`    | CSS compilado (se genera, no se edita a mano)                 |
| `public/assets/js/`        | JS nativo: `app.js`, `components/*.js` y `bootzen.js` (runtime) |
| `bin/bootzen`              | CLI del proyecto                                              |
| `storage/`                 | Logs, sesiones, cachés y backups (escribible, fuera de git)   |
| `config/session.php`       | Sesión (archivos en `storage/sessions`), cookie y CSRF        |
| `config/auth.php`          | Modelo de usuario y página de inicio tras el login (`home_url()`) |
| `config/admin.php`         | Menú del panel `/admin` (lo crea `make:admin`)                |
| `app/Controllers/Admin/`   | Controladores del panel de administración (`make:admin`)      |
| `config/security.php`      | Content-Security-Policy y HSTS                                |
| `app/Jobs/`                | Jobs para la cola (`make:job`), se encolan con `dispatch()`   |
| `routes/schedule.php`      | Tareas programadas; las ejecuta `schedule:run` desde cron     |
| `config/storage.php`       | Discos de archivos: local, `s3` (y compatibles), `ftp` y `sftp` |
| `config/queue.php`         | Cola: `sync` (en el momento) o `database` (tabla `jobs`)      |
| `config/mail.php`          | Correo: `log` (en `storage/logs`), `smtp` (envío real) o `array` (tests) |
| `app/Models/`              | Modelos (`App\Models`), uno por tabla                         |
| `config/database.php`      | Conexión: SQLite por defecto, MySQL opcional                  |
| `database/migrations/`     | Migraciones con SQL directo, en orden de nombre de archivo    |
| `database/seeds/`          | Datos de ejemplo: `DatabaseSeeder.php` y los que llame        |
| `database/factories/`      | Factories: `TareaFactory.php` para `Tarea::factory()` (tests y seeders) |
| `database/app.sqlite`      | Base SQLite local (fuera de git)                              |
| `tests/`                   | Tests con Pest (`Feature/` y `Unit/`)                         |
| `docs/ai/`                 | Recetas (`recipes/`) y referencia de la API (`reference.md`)  |
| `public/uploads/`          | Archivos subidos públicos (`storePublic`, disco `public`)     |
| `storage/app/`             | Archivos subidos privados (`store`, disco `local`)            |
| `framework/`               | BootZen. **No editar**: se actualiza con `framework:update`   |
| `deploy/`                  | Nginx, Caddy, pool de PHP-FPM, `deploy.sh` y archivos de Docker |
| `Dockerfile`, `docker-compose.yml` | Imagen de producción (guía en `docs/deploy/docker.md`) |
| `.htaccess` (raíz)         | Manda todo a `public/` en hostings donde no puedes cambiar la raíz |
| `build/`                   | Artefactos de `deploy:build` (fuera de git)                   |

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
php bin/bootzen make:resource Tarea titulo:string hecha:bool  # CRUD completo (ver abajo)
php bin/bootzen make:admin Producto nombre:string precio:float # panel /admin (con --auth)
php bin/bootzen make:model Cliente --migration   # también: make:controller (--json), make:migration,
                                  # make:middleware, make:seeder, make:factory, make:policy, make:test,
                                  # make:component
php bin/bootzen route:list        # rutas con nombre, middleware y acción
php bin/bootzen doctor            # revisa PHP, extensiones, permisos, .env y base de datos
php bin/bootzen config:cache      # configuración en un archivo (producción; config:clear la borra)
php bin/bootzen key:generate      # APP_KEY en .env
php bin/bootzen mail:test tu@correo.com  # envía un correo de prueba con la configuración actual
php bin/bootzen user:role ana@correo.com admin  # cambia el rol (roles en config/auth.php)
php bin/bootzen make:job EnviarFactura   # job en app/Jobs para dispatch()
php bin/bootzen queue:table       # migración de la cola (para QUEUE_DRIVER=database)
php bin/bootzen queue:work        # atiende la cola (--stop-when-empty, --max-time=50)
php bin/bootzen queue:failed      # jobs fallidos; queue:retry <id|all> y queue:flush
php bin/bootzen schedule:run      # tareas de routes/schedule.php que tocan (lo llama cron cada minuto)
php bin/bootzen schedule:list     # tareas, próxima ejecución y la línea de cron
php bin/bootzen deploy:build      # artefacto para el servidor en build/ (--version=1.2.0, --no-css)
php bin/bootzen framework:update  # actualiza framework/ desde ~/.bootzen (--from=ruta, --force)
composer install                  # solo la primera vez, instala herramientas de dev
composer check                    # formato + PHPStan + tests. Debe pasar siempre (o php bin/bootzen check)
composer format                   # corrige el formato
composer test                     # solo tests
```

La primera vez que se compila el CSS, `bin/bootzen` descarga el binario de
Tailwind en `storage/bin/` y verifica su SHA-256 (necesita red esa vez). No
hace falta Node. Una clase de Tailwind que ninguna vista usaba no aparece en
`public/assets/app.css` hasta recompilar.

**Antes de dar una tarea por terminada, ejecuta `composer check` y deja todo
en verde.**

## Cómo se hacen las cosas

### Crear un CRUD: empieza siempre por make:resource

```bash
php bin/bootzen make:resource Tarea titulo:string notas:text:nullable hecha:bool vence:date:nullable
php bin/bootzen migrate
composer check
```

Crea la migración, el modelo, el controlador con validación, la ruta
`resource` en `routes/web.php`, las vistas `index`, `create`, `edit` y `show`
con componentes, `database/factories/TareaFactory.php` y
`tests/Feature/TareaTest.php`. El resultado pasa
`composer check` sin tocar nada; después ajústalo a lo que pide la tarea.

- Tipos: `string`, `text`, `email`, `int`, `float`, `bool`, `date` y
  `datetime`. Agrega `:nullable` para campos opcionales.
- La tabla, las rutas y las vistas usan el plural **en inglés** del modelo
  (`Task` → `tasks`, `Category` → `categories`; `Tarea` → `tareas` también
  funciona). Si el plural no sale bien (`Ciudad` → `ciudads`), usa `--table=`:
  `make:resource Ciudad nombre:string --table=ciudades` nombra la tabla, las
  rutas, las vistas y la variable en plural como `ciudades`.
- Nunca sobrescribe archivos: si algo ya existe, se detiene antes de escribir.
- Panel de administración: `make:admin Producto nombre:string activo:bool`
  (proyectos `--auth`) genera lista con búsqueda, filtros, orden y CSV, alta,
  edición y borrado en `/admin/productos`, solo para `role:admin`. Receta:
  `docs/ai/recipes/panel-admin.md`.
- Para lo demás: `make:model` (`--migration`, `--table=`), `make:controller`,
  `make:migration crear_facturas` (trae el `CREATE TABLE`), `make:middleware`,
  `make:seeder`, `make:factory`, `make:policy`, `make:test` (`--unit`) y
  `make:component modal` (`--class`).

### Rutas

```php
// routes/web.php
return static function (Router $router): void {
    // Páginas en cada idioma de APP_LOCALES: /tareas (por defecto) y /en/tareas
    $router->localized(function (Router $router): void {
        $router->get('/', [HomeController::class, 'index'])->name('home');
    });

    // CRUD completo con nombres tareas.index, tareas.create, tareas.store,
    // tareas.show, tareas.edit, tareas.update y tareas.destroy
    $router->resource('tareas', TareaController::class);

    // Restricción en línea: solo números
    $router->get('/facturas/{id:\d+}', [FacturaController::class, 'show'])->name('facturas.show');

    // Solo usuarios con sesión iniciada; los invitados van a la ruta 'login'
    $router->group(['prefix' => '/admin', 'as' => 'admin.', 'middleware' => 'auth'], function (Router $router): void {
        $router->get('/', [AdminController::class, 'index'])->name('index'); // admin.index
    });

    // Límite por IP: 10 peticiones por minuto
    $router->post('/contacto', [ContactoController::class, 'store'])->name('contacto.store')->middleware('throttle:10,1');
};
```

```php
// routes/api.php: todo lleva /api, nombres api.* y el grupo api (sin sesión ni CSRF)
return static function (Router $router): void {
    $router->get('/tareas', [TareaApiController::class, 'index'])->name('tareas.index'); // GET /api/tareas
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
- Middleware disponibles: `auth` (solo usuarios), `guest` (solo invitados),
  `throttle:max,minutos`, `cors`, `locale` (idioma, ver Idiomas), `verified`
  (correo verificado), `signed` (enlaces de `signed_route()`), `role:admin`
  (alguno de los roles) y `can:reportes.ver` (permiso del rol). Grupos: `web`
  (sesión, "recordarme" y CSRF; todas las rutas de `web.php`) y `api` (todas
  las de `api.php`).

### Controladores

```php
final class TareaController
{
    public function show(int $id): Response
    {
        return view('tareas.show', ['tarea' => Tarea::findOrFail($id)]);
    }

    public function store(Request $request): Response
    {
        $datos = $request->validate([
            'titulo' => 'required|string|max:120',
            'prioridad' => 'nullable|in:alta,media,baja',
        ]);

        $tarea = Tarea::create($datos);

        return redirect(route('tareas.show', ['id' => $tarea->id]))->with('status', __('Tarea creada.'));
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
  `<x-alert>`, `<x-form>`, `<x-input>`, `<x-textarea>`, `<x-checkbox>` y
  `<x-pagination>`. Úsalos antes de crear otros.

### Formularios y validación

```php
<x-form :action="route('tareas.store')">          {{-- agrega @csrf solo --}}
    <x-input name="titulo" label="Título" required />  {{-- muestra el error y old('titulo') --}}
    <x-button type="submit">Guardar</x-button>
</x-form>
```

- `$request->validate([...])` devuelve solo los campos validados. Si falla,
  vuelve solo a la página anterior con los errores y el input (formularios) o
  responde 422 con `{"errors": {...}}` (peticiones JSON). No hace falta
  `try/catch`.
- Reglas: `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`,
  `array`, `email`, `url`, `min:n`, `max:n`, `between:a,b`, `in:a,b`,
  `confirmed` (busca `campo_confirmation`), `date`, `regex:/patrón/`,
  `unique:tabla,columna,idIgnorado`, `exists:tabla,columna`, `file`, `image`,
  `mimes:pdf,jpg`. `min`/`max` miden caracteres, valor (con `numeric` o
  `integer`), elementos (arrays) o KB (archivos). Listas con comodín:
  `'items.*.precio' => 'required|numeric'`.
- Sin `required`, un campo vacío se acepta y queda como `null`. Cada campo
  muestra solo su primer error. Mensajes en español; propios con el segundo
  argumento: `['titulo.required' => 'Ponle un título.']`.
- Reglas propias con un closure: `fn (mixed $valor, string $campo): ?string`
  que devuelve el mensaje de error o `null`.
- Errores a mano: `throw ValidationException::withMessages(['email' => '...'])`.
- En vistas: `@error('campo') {{ $message }} @enderror`, `$errors` (campo =>
  mensajes) y `old('campo')`. `<x-input>`, `<x-textarea>` y `<x-checkbox>` ya
  hacen las tres cosas. `<x-checkbox>` envía `0` si no está marcada, así la
  regla `boolean` siempre recibe un valor.
- Si usas una regla con `|` dentro (regex), pasa las reglas como lista:
  `['required', 'regex:/^(a|b)$/']`.

### Sesión y mensajes flash

```php
return redirect(route('tareas.index'))->with('status', 'Tarea borrada.'); // el layout lo muestra
return back()->withErrors(['archivo' => 'No se pudo leer.'])->withInput($request->except(['password']));
session('status');                 // leer
session()->put('carrito', [3, 7]); // guardar (solo escalares y arrays)
session()->forget('carrito');
```

- La sesión existe en las rutas de `web.php`. En `api.php` no hay sesión.
- `with()` guarda datos solo para la siguiente petición (flash).

### Autenticación

Con `bootzen-new mi-app --auth` el proyecto trae registro, login, logout y
recuperación de contraseña listos (`app/Controllers/Auth`, `app/Models/User.php`,
`routes/auth.php`, vistas en `resources/views/auth`, tests en
`tests/Feature/AuthTest.php`). Sin `--auth`, crea esos archivos siguiendo los
mismos patrones.

```php
auth()->user();                           // usuario actual o null
auth()->id();                             // id sin consultar la base
auth()->check();                          // true si hay sesión iniciada
auth()->attempt(['email' => $email, 'password' => $password]); // true si coincide e inicia sesión
auth()->login($usuario);                  // inicia sesión (cambia el id de sesión)
auth()->attempt($credenciales, $request->boolean('remember')); // "recordarme" 30 días en este dispositivo
auth()->logout();
auth()->logoutOtherDevices($password);    // cierra las demás sesiones; false si la contraseña no coincide
auth()->forgetRememberedDevices($usuario); // borra sus "recordarme" (al restablecer la contraseña)
redirect(auth()->intended(route('dashboard'))); // vuelve a donde iba antes del login
$usuario->password = Hash::make($password);     // BootZen\Auth\Hash: Argon2id o bcrypt
```

- Protege rutas con `->middleware('auth')`; las de login y registro con
  `'guest'`. Con `--auth`, las páginas privadas llevan `['auth', 'verified']`:
  quien no verificó su correo va a `/verificar-correo`
  (`App\Controllers\Auth\EmailVerificationController`, correo en
  `App\Mail\VerificationEmail`).
- Cambiar la contraseña cierra solas las demás sesiones abiertas.
- **Roles y permisos**: cada usuario tiene un rol (columna `role`, por
  defecto `usuario`; nunca en `$fillable`). Los permisos de cada rol están en
  `config/auth.php` (`'admin' => ['*']`, `'editor' => ['tareas.*']`).
  `can('reportes.ver')`, `$user->hasRole('admin')`, middleware `role:admin` y
  `can:reportes.ver`. El primer admin: `php bin/bootzen user:role correo admin`.
- **Políticas**: "cada quien edita lo suyo" va en `app/Policies/TareaPolicy.php`
  (`make:policy Tarea`), con métodos `view`, `update`, `delete`... que reciben
  `(User $user, Tarea $tarea)`. En el controlador `authorize('update', $tarea)`
  (403, o `authorize('update', $tarea, 404)` para no revelar que existe); en
  vistas `@if (can('update', $tarea))`. El rol con `'*'` pasa todas. Ejemplo
  completo en `docs/ai/recipes/proteger-rutas.md`.
- Enlaces que se mandan por correo y funcionan sin sesión:
  `signed_route('nombre', ['id' => 5], 60)` (minutos) y la ruta con
  `->middleware('signed')`; un parámetro cambiado o un enlace vencido da 403.
- Login con límite de intentos: `BootZen\Security\RateLimiter` (`hit`,
  `tooManyAttempts`, `availableIn`, `clear`), ver `LoginController`.
- Correo: `mailer()->send(new Message($email, 'Asunto', $texto, $html))`, con
  `->cc()`, `->bcc()`, `->replyTo()`, `->attach($ruta)` y `->attachData()`
  encadenables. `MAIL_DRIVER=log` lo escribe en `storage/logs`; `smtp` lo
  envía (STARTTLS, TLS, auth). Si falla lanza `MailException`: atrápala donde
  un correo no deba romper la página. Receta: `docs/ai/recipes/enviar-correo.md`.

### Seguridad que ya viene puesta

- CSRF en todas las rutas de `web.php`: los formularios llevan `@csrf` (o
  usan `<x-form>`) y `bootzen.js` manda la cabecera `X-CSRF-TOKEN`. Un token
  inválido responde 419. Para webhooks usa `csrf_except` en
  `config/session.php`.
- Cabeceras de seguridad en todas las respuestas (`SecurityHeaders`) con una
  CSP que solo permite recursos del propio dominio. Si cargas algo externo
  (fuentes, analytics), agrégalo en `config/security.php`.
- Cookies de sesión `HttpOnly`, `SameSite=Lax` y `Secure` en HTTPS. El id de
  sesión cambia al iniciar y cerrar sesión.

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
- Buscador: `->whereLike(['nombre', 'email'], $texto)` (los `%` y `_` del
  usuario se buscan literales).
- Tablas grandes: `foreach (Pedido::query()->orderBy('id')->cursor() as $p)`
  recorre por bloques sin cargar todo.
- `update()` y `delete()` sin `where` fallan a propósito.

**Seeders.** `database/seeds/DatabaseSeeder.php` retorna una clase anónima
que extiende `BootZen\Database\Seeder`; `$this->call('Otro')` ejecuta
`database/seeds/Otro.php`.

**Factories.** Datos de ejemplo para tests y seeders, en
`database/factories/{Modelo}Factory.php` (`make:factory Tarea`; `make:resource`
ya la crea):

```php
// database/factories/TareaFactory.php
final class TareaFactory extends Factory
{
    protected string $model = Tarea::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),          // crea al dueño si no le pasas user_id
            'titulo' => fake()->sentence(),
            'hecha' => fake()->boolean(30),         // 30% de las veces true
            'vence' => fake()->date('now', '+1 month'),
        ];
    }
}
```

```php
$tarea = Tarea::factory()->create();                                // una, con su usuario
Tarea::factory()->count(20)->create(['user_id' => $ana->id]);      // lista de 20
Tarea::factory()->state(['hecha' => true])->make();                // sin guardar
Tarea::factory()->count(3)->state(fn (array $v, int $i) => ['titulo' => "Tarea {$i}"])->create();
```

- La factory asigna todas las columnas, también las que no están en
  `$fillable`. Los valores que pasas a `create()` ganan.
- Estados propios (`public function hechas(): self { return $this->state([...]); }`)
  se llaman como `Tarea::factory()->hechas()`; para que PHPStan los conozca, el
  modelo lleva `@method static \Database\Factories\TareaFactory factory()`
  (`make:resource` ya lo pone).
- `fake()`: `name()`, `firstName()`, `lastName()`, `email()`, `userName()`,
  `phoneNumber()`, `company()`, `address()`, `city()`, `country()`, `word()`,
  `words(3)`, `sentence()`, `paragraph()`, `text(200)`, `number(1, 10)`,
  `decimal(1, 100)`, `boolean(30)`, `date()`, `dateTime()`,
  `randomElement([...])`, `uuid()`, `url()`. `fake()->unique()->email()` no
  repite. Salen en el idioma activo; los correos usan `example.com`.
- En tests la semilla es fija: cada `client()` da los mismos valores, así un
  test que falla falla siempre igual.

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
$request->file('foto')?->resize(1600)->storePublic('clientes');  // 'uploads/clientes/3f9c...jpg' (ver subir-archivos.md)
```

### Respuestas y errores

```php
return Response::html($html);
return Response::json(['data' => $items], 201);
return Response::noContent();
return Response::stream(function (): void { echo 'línea'; }, 200, ['content-type' => 'text/plain']); // se escribe al enviarse
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

### Idiomas

- Textos visibles con `__('Texto en español')`; el español es la clave y
  `lang/en.php` tiene `'Texto en español' => 'English text'`. Marcadores:
  `__('Hola, :nombre', ['nombre' => $n])`; plurales:
  `trans_choice('Un pedido|:count pedidos', $n)`. Sin traducción sale en
  español.
- Idiomas en `.env`: `APP_LOCALE=es` (sin prefijo) y `APP_LOCALES=es,en`.
  Las páginas van dentro de `$router->localized()` (`/precios` y
  `/en/precios`); `route()` elige la del idioma activo. `make:resource` ya
  agrega su ruta ahí.
- URLs en inglés: `lang/en/rutas.php` traduce segmentos (`'precios' =>
  'pricing'` da `/en/pricing`); la URL sin traducir redirige con 301.
- Layout: `lang="{{ locale() }}"` y `{{ hreflang_links() }}` ya vienen.
  Cambiar de idioma: `<a href="{{ locale_url('en') }}">English</a>`.
- Formatos del idioma activo: `format_date($fecha)` (`'short'`, `'medium'`,
  `'long'`, con hora), `format_number($n, 2)`, `format_money($n)`
  (`APP_CURRENCY`). Receta: `docs/ai/recipes/idiomas.md`.

### Archivos

- Guarda con `$request->file('x')->store('carpeta')` (privado) o
  `->storePublic('carpeta')` (público) y guarda **la ruta** en la base.
  Muestra con `file_url($ruta)`; entrega privados con
  `temporary_url($ruta, 10)` o `storage()->download($ruta)`.
- Imágenes: `->resize(1600)` o `->cover(300, 300)` antes de guardar (quitan
  EXIF).
- El destino se elige en `.env` (`STORAGE_DISK`, `STORAGE_PUBLIC_DISK`):
  disco local, S3 o compatibles (R2, Spaces, B2, MinIO), FTP o SFTP, sin
  cambiar el código. Para un disco concreto: `storage('s3')->put()`, `get()`,
  `exists()`, `delete()`, `size()`. Receta: `docs/ai/recipes/subir-archivos.md`.

### Cola y tareas programadas

- Trabajo lento o que puede fallar (correos, PDFs, APIs externas) va a un
  job: `php bin/bootzen make:job Nombre`, parámetros del constructor
  `public readonly` con valores simples (ids, no modelos), y
  `dispatch(new Nombre($id))`. Correos: `mailer()->queue($message)`.
- `QUEUE_DRIVER=sync` (por defecto y en tests) ejecuta en el momento;
  `database` necesita `queue:table` + `migrate` y alguien que corra
  `queue:work`. Reintentos con `$tries`/`$backoff`, fallidos en
  `failed_jobs`. Receta: `docs/ai/recipes/cola-de-trabajos.md`.
- Tareas periódicas en `routes/schedule.php`:
  `$schedule->command('db:backup')->dailyAt('03:00')`, `->job(...)`,
  `->call(fn, 'nombre')`. El servidor solo necesita la línea de cron que
  imprime `schedule:list`. Receta: `docs/ai/recipes/tareas-programadas.md`.

### Despliegue

- `deploy:build` arma `build/<app>-<versión>/` y su `.zip` sin `.env`, base,
  tests ni `vendor/`, con el CSS compilado y las vistas precompiladas.
- Guías probadas en `docs/deploy/`: hosting compartido (con el instalador web),
  VPS (`deploy/deploy.sh`, Nginx o Caddy) y Docker (`docker compose up -d --build`).
- Instalador web para hostings sin terminal: `INSTALL_ENABLED=true` habilita
  `/_bootzen/instalar`, que pide la `APP_KEY`, migra y se bloquea solo. Déjalo en
  `false` el resto del tiempo (`doctor` avisa si sigue activo).
- En producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` propia y
  `php bin/bootzen doctor` en verde.
- Si cambias archivos de `deploy/` o el `Dockerfile`, prueba el escenario
  completo; no son parte de `composer check`.

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
Ya existen los alias `auth`, `guest`, `throttle` y `cors`, y los grupos `web`
y `api`. `make:middleware Nombre` crea la clase en `app/Middleware`.

### Tests

Cada funcionalidad nueva lleva al menos un test de feature. `client()` crea
una aplicación con una base SQLite en memoria (todas las migraciones
aplicadas, vacía en cada llamada) y un cliente HTTP que conserva la cookie de
sesión entre peticiones, como un navegador. En tests (`APP_ENV=testing`) no
se verifica el CSRF y la sesión, la caché y el correo viven en memoria.

```php
it('crea una tarea', function (): void {
    $client = client();                       // primero el cliente: crea la base de datos

    $client->post('/tareas', ['titulo' => 'Comprar pan'])
        ->assertRedirect('/tareas/1')
        ->assertSessionHas('status', 'Tarea creada.');

    $client->assertDatabaseHas('tareas', ['titulo' => 'Comprar pan']);
});

it('valida el título', function (): void {
    client()->from('/tareas/create')->post('/tareas', [])
        ->assertRedirect('/tareas/create')
        ->assertSessionHasErrors(['titulo']);
});

it('solo deja entrar a usuarios', function (): void {
    $client = client();
    $usuario = User::factory()->create();     // los modelos van después de client()

    $client->get('/panel')->assertRedirect('/login');
    $client->actingAs($usuario)->get('/panel')->assertOk()->assertSee('Hola');
});
```

- Peticiones: `get`, `post`, `put`, `patch`, `delete`, `getJson`,
  `postJson`, `putJson`, `deleteJson`; `from('/pagina')` (de dónde viene el
  formulario), `actingAs($usuario)`, `withHeaders([...])`,
  `followingRedirects()`, `closeBrowser()` (olvida la cookie de sesión y
  conserva la de "recordarme") y `cookies()`.
- Respuesta: `assertOk`, `assertStatus`, `assertCreated`, `assertNoContent`,
  `assertNotFound`, `assertForbidden`, `assertUnauthorized`,
  `assertUnprocessable`, `assertRedirect('/ruta')`, `assertSee` (escapa como
  `{{ }}`), `assertDontSee`, `assertJson([...])`, `assertJsonPath('data.0.id', 1)`,
  `assertJsonCount`, `assertHeader`, `assertSessionHas`,
  `assertSessionMissing`, `assertSessionHasErrors([...])`,
  `assertSessionHasNoErrors`; `json()`, `body()`, `status()`.
- Cliente: `assertDatabaseHas`, `assertDatabaseMissing`,
  `assertDatabaseCount`, `assertAuthenticated`, `assertGuest`. Los correos
  enviados: `$client->app->make(Mailer::class)` es un `ArrayMailer` con `sent()`.
- Helpers que usan varios archivos de test (por ejemplo, un usuario con
  sesión) van en `tests/Pest.php`. En Pest las funciones de un archivo de
  test son globales: ponles un nombre propio del archivo para que no choquen.
- Para mirar una ruta a mano usa `php bin/bootzen serve` (o `route:list`).
  Fuera de los tests se usa la base de `.env` (`database/app.sqlite`), no la
  de memoria: un script suelto que cargue `tests/Pest.php` escribe ahí.

## API disponible del framework

- **Router**: `get`, `post`, `put`, `patch`, `delete`, `options`, `match`,
  `any`, `resource`, `localized(fn)`, `group(['prefix', 'as', 'middleware'], fn)`; en la ruta:
  `->name()`, `->middleware()`, `->where()`.
- **Request**: `validate`, `file`, `session`, `input`, `all`, `only`, `except`, `has`,
  `filled`, `boolean`, `integer`, `param`, `params`, `getRoute`, `getMethod`, `getPath`,
  `getQuery`, `getBody`, `getHeader`, `getFile`, `getCookie`, `wantsJson`,
  `isJson`, `isAjax`, `isSecure`, `getClientIp`.
- **Response**: `html`, `json`, `redirect`, `noContent`, `stream`, `with`,
  `withErrors`, `withInput`, `withHeader`, `withHeaders`, `withCookie`,
  `withStatus`.
- **Sesión** (`session()`): `get`, `put`, `has`, `forget`, `pull`, `flash`,
  `all`, `token`.
- **Auth** (`auth()`): `user`, `id`, `check`, `guest`, `attempt`, `validate`,
  `login`, `loginUsingId`, `logout`, `logoutOtherDevices`,
  `forgetRememberedDevices`, `intended`. `Hash::make`, `Hash::check`.
- **Vistas**: `view()`, `class_list()`; en componentes `$attributes` con
  `merge`, `class`, `only`, `except`, `has`, `get`.
- **Model**: `query`, `all`, `find`, `findOrFail`, `where`, `create`, `fill`,
  `save`, `update`, `delete`, `refresh`, `toArray`, `getKey`, `isDirty`,
  `hasMany`, `belongsTo`, `factory`.
- **Consultas** (QueryBuilder y ModelQuery): `select`, `selectRaw`, `where`,
  `orWhere`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `whereRaw`,
  `join`, `leftJoin`, `orderBy`, `latest`, `limit`, `offset`, `get`, `first`,
  `firstOrFail`, `find`, `value`, `pluck`, `count`, `exists`, `paginate`,
  `whereLike`, `cursor`, `insert`, `update`, `delete`.
- **Helpers**: `db()`, `auth()`, `session()`, `old()`, `back()`, `mailer()`,
  `storage()`, `file_url()`, `temporary_url()`, `dispatch()`, `queue()`,
  `config()`, `env()`, `route()`, `url()`, `asset()`, `app()`, `logger()`,
  `cache()`, `base_path()`, `public_path()`, `storage_path()`, `e()`,
  `redirect()`, `abort()`, `json_response()`, `download()`, `csrf_token()`,
  `csrf_field()`, `validate_csrf_token()`, `slugify()`, `str_random()`,
  `__()`, `trans_choice()`, `locale()`, `locale_url()`, `hreflang_links()`,
  `format_date()`, `format_number()`, `format_money()`, `home_url()`, `fake()`,
  `signed_route()`, `can()`, `authorize()`, `gate()`, `current_path()`.

## Lo que todavía NO existe

Estas piezas todavía no vienen en BootZen; **no las inventes ni instales
librerías para suplirlas** sin preguntar:

- `route:cache`: el router compila las rutas en cada petición y es rápido
  igual.
- Colas en Redis u otros servicios: la cola es `sync` o `database`.

## Reglas

- Para un CRUD nuevo usa `make:resource`; no lo escribas a mano desde cero.
- No agregues dependencias de Composer en `require`. Solo `require-dev`.
- No edites `framework/`. Si algo del framework falla, repórtalo.
- No pongas HTML dentro de controladores: va en `resources/views/` con `view()`.
- Imprime con `{{ }}` (escapa); `{!! !!}` solo para HTML que tú generaste.
- No edites `public/assets/app.css` ni `public/assets/js/bootzen.js`.
- Nunca concatenes datos del usuario en SQL: usa parámetros (`?`).
- Valida toda entrada con `$request->validate()` antes de usarla.
- Contraseñas siempre con `Hash::make()`; nunca en texto plano ni en logs.
- No desactives el CSRF ni quites `@csrf` de un formulario.
- No uses PDO directo: `db()`, el query builder o los modelos.
- Nada de `exit` ni `die`: retorna un `Response` o usa `abort()`.
- No leas `env()` fuera de `config/`.
- Todo texto que ve el usuario va con `__()`, también los mensajes flash.
