# Plan: BootZen como boilerplate de MVPs monolíticos

> Este documento reemplaza la dirección de `01_PLAN-MAESTRO.md` (PHAR + plugins
> Composer) y `02_PLAN-DESARROLLO.md` (fases por módulos). Ambos se conservan
> como referencia histórica, pero el rumbo actual es este.

## Resumen

BootZen pasa de ser "microframework distribuido en un `.phar`" a ser un
**boilerplate de aplicaciones monolíticas** listo para construir MVPs con ayuda
de IA generativa. El stack queda cerrado y sin sorpresas:

- **PHP 8.2+** sin dependencias de Composer en producción.
- **SQLite** como base de datos por defecto (MySQL opcional vía PDO).
- **Tailwind CSS v4** con CLI standalone (Node opcional, nunca obligatorio).
- **JavaScript nativo** con módulos ES, sin bundler.
- **Plantillas `.php` con componentes `<x-*>`** compiladas y cacheadas.
- Despliegue en **hosting compartido, VPS o Docker** con el mismo artefacto.

La idea central: que una persona (o un agente de IA) pueda pedir "crea un CRUD
de tareas con login" y obtener código que siga una sola forma de hacer las
cosas, pase `bootzen check` y se despliegue con un comando.

---

## 1. Decisiones tomadas

| Tema                    | Decisión                                                    | Por qué                                                                                       |
| ----------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| Distribución            | El código del framework vive **dentro** de cada proyecto    | La IA puede leerlo; no hay problemas con hostings que bloquean `phar://`; se depura fácil     |
| Plantillas              | Vistas `.php` + etiquetas `<x-*>` compiladas a PHP          | Sintaxis familiar (tipo Blade) que los modelos de IA ya escriben bien; HTML fuera de clases   |
| Planes previos          | Este plan los reemplaza                                     | La arquitectura de plugins no encaja con un monolito MVP                                      |
| Dependencias en runtime | **Cero**                                                    | Subir por FTP a un hosting compartido y que funcione; menos superficie de fallos              |
| Dependencias de dev     | Pest, PHPStan, PHP CS Fixer                                 | Se quedan; son la red de seguridad del código que genera la IA                                |
| CSS                     | Tailwind v4 con binario standalone                          | Sin Node en el servidor; el CSS compilado viaja en el artefacto de despliegue                 |
| JS                      | Módulos ES nativos + runtime propio de ~3 KB                | Sin build step, sin `node_modules` para el usuario final                                      |
| Base de datos           | SQLite (WAL) por defecto, MySQL opcional                    | Un archivo, cero administración, suficiente para la gran mayoría de MVPs                      |
| Namespaces              | `BootZen\` para el framework, `App\` para la aplicación     | Hoy el scaffold mapea `BootZen\` a `src/` del proyecto y choca con el framework               |

---

## 2. Diagnóstico del estado actual (v1.1.0)

Antes de construir encima, esto es lo que hay que saber del código actual.
Ninguno de estos puntos es grave por sí solo, pero juntos explican por qué hoy
no se puede arrancar un proyecto real.

### Bloqueantes

1. **El `.phar` no carga.** `core/stub.php` hace
   `require 'phar://bootzen.phar/index.php'` y ese archivo no existe. Además el
   autoloader busca en `phar://bootzen.phar/src/`, pero `build-phar.php`
   empaqueta `core/src` en la raíz del phar, así que tampoco encontraría las
   clases. Verificado: incluir el phar termina en `Fatal error`.
2. **Choque de namespaces en el scaffold.** El `composer.json` generado por
   `init_project.sh` mapea `BootZen\` a `src/` del proyecto, el mismo namespace
   del framework.
3. **Scripts que no existen.** `composer.json` del scaffold llama a
   `.scripts/make.php`, pero solo se copia `make.sh`.
4. **Tailwind mezclado.** `package.json` instala Tailwind `^3.3` mientras
   `resources/css/app.css` usa `@plugin`, que es sintaxis de v4.

### Deuda técnica relevante

- **Router** (`core/src/Core/Router.php`): guarda los parámetros de ruta en el
  objeto `Route` (estado compartido entre peticiones), no escapa el patrón
  antes de convertirlo a regex, no tiene rutas con nombre, no responde 405 y
  compila la regex dos veces por petición.
- **Request**: no tiene atributos de ruta, ni `input()`, ni validación, ni
  acceso a sesión.
- **Helpers** (`core/src/Helpers/helpers.php`): redefine `rand`, `mt_rand`,
  `random_bytes` y `random_int` como "polyfills" (en PHP 8.2 siempre existen y
  los fallbacks son inseguros); `csrf_token()` tiene ramas duplicadas;
  `redirect()` y `abort()` hacen `exit`, lo que rompe el pipeline de
  middleware y hace imposible testearlos.
- **Component** (`core/src/Core/Component.php`): el HTML se arma
  concatenando strings dentro de clases y la caché depende de Redis.
- **Ejemplos dentro del core**: `HomeController.php` (348 líneas con contenido
  personal) y `ApiRoutes.php` viven en el framework.
- **Dependencias innecesarias**: `predis`, `monolog`, `phpdotenv` y
  `var-dumper` como requeridas.
- **Scaffold en heredocs**: `init_project.sh` genera ~25 archivos dentro de
  895 líneas de bash, imposible de testear o revisar con calma.
- **Sin tests ni CI de calidad**: no hay `tests/` en el repo y el único
  workflow es el de release.
- **Documentación inconsistente**: el README dice que el phar se copia a
  `public/`, el scaffold lo copia a la raíz.

### Lo que sí se rescata

- `Container` con autowiring por reflexión: sólido, se queda casi igual.
- `Request` y `Response` inmutables: buena base, solo hay que ampliarlos.
- La idea del middleware con `process($request, $next)`.
- Los componentes UI (Alert, Button, Card, Form, Input, Layout, Navigation):
  su diseño Tailwind se migra a componentes de vista `<x-*>`.
- `docs/test-plans/core-framework.md`: se adapta a la nueva estructura.

---

## 3. Arquitectura objetivo

### 3.1 Estructura del repositorio BootZen

El repo deja de generar archivos con heredocs. El boilerplate pasa a ser una
carpeta real, versionada y testeable.

```
bootzen/
├── framework/              # Código del framework (namespace BootZen\)
│   ├── src/
│   └── bootstrap.php       # Autoloader propio + carga de helpers
├── skeleton/               # El boilerplate tal cual se copia a un proyecto nuevo
├── tests/                  # Tests del framework (Pest)
├── bin/bootzen-new         # Instalador: copia skeleton/ + framework/
├── docs/                   # Documentación humana
├── composer.json           # Solo dev: Pest, PHPStan, CS Fixer
└── .github/workflows/      # ci.yml (tests) + release.yml (tarball)
```

`init_project.sh` queda como un envoltorio delgado de `bin/bootzen-new` (o se
elimina) para no romper a quien ya lo usa.

### 3.2 Estructura de un proyecto generado

```
mi-app/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Middleware/
│   ├── Services/
│   └── View/Components/     # Componentes con lógica (opcional)
├── bootstrap/app.php        # Crea la Application, registra middleware
├── config/                  # app.php, database.php, session.php, mail.php
├── database/
│   ├── migrations/
│   ├── seeds/
│   └── app.sqlite           # Ignorado en git
├── framework/               # BootZen, copiado del repo (se actualiza con un comando)
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── assets/              # CSS compilado y JS servido tal cual
├── resources/
│   ├── css/app.css
│   ├── js/                  # app.js + components/*.js
│   └── views/
│       ├── layouts/
│       ├── components/      # Componentes anónimos <x-*>
│       ├── pages/
│       └── errors/          # 404.php, 419.php, 500.php...
├── routes/
│   ├── web.php
│   └── api.php
├── storage/                 # cache/views, logs, sessions, uploads
├── tests/
├── bin/bootzen              # CLI del proyecto
├── docker/                  # Dockerfile y configs
├── AGENTS.md                # Guía para agentes de IA (con CLAUDE.md apuntando aquí)
├── docs/ai/                 # Referencia compacta del framework para IA
├── composer.json            # Solo require-dev + autoload
└── .env.example
```

### 3.3 Flujo de arranque

```
public/index.php
  → framework/bootstrap.php   (autoloader BootZen\ y App\, sin Composer)
  → bootstrap/app.php         (Application, config, middleware, rutas)
  → Application::handle()     (pipeline global → Router → handler → Response)
```

Si existe `vendor/autoload.php` se usa (útil en desarrollo), pero **no es
requisito** en producción. Esto es lo que permite subir el proyecto por FTP.

---

## 4. Módulos del framework

Cada módulo lista lo mínimo que necesita un MVP. Lo que no está aquí no entra
en v2.0.

### 4.1 Núcleo

| Pieza          | Qué hace                                                                                           |
| -------------- | -------------------------------------------------------------------------------------------------- |
| `Application`  | Recibe `basePath`, carga `.env` y config, registra providers internos, maneja excepciones          |
| `Container`    | Se mantiene. Agregar `call()` para inyectar dependencias en métodos de controladores               |
| `Env`          | Parser propio de `.env` (sin phpdotenv): claves, comillas, comentarios, interpolación simple       |
| `Config`       | Archivos PHP que retornan arrays, acceso con `config('app.name')`, caché en un solo archivo         |
| `Logger`       | Archivo diario, niveles PSR-3, contexto de request. Sin Monolog                                    |
| `Cache`        | Driver de archivo (y APCu si está disponible). Se elimina Redis/Predis                             |

### 4.2 HTTP

- **Router**: compilar cada ruta a regex una sola vez y escapar el patrón;
  restricciones (`{id:\d+}`); rutas con nombre y helper `route('posts.show',
  ['id' => 1])`; 405 con cabecera `Allow`; grupos con prefijo, nombre y
  middleware; `resource()` para CRUD; caché de rutas para producción.
- **Request**: atributos (`$request->param('id')`), `input()`, `only()`,
  `boolean()`, `file()`, `validate()`, `session()`, `user()`, soporte de
  `_method` para PUT/PATCH/DELETE desde formularios.
- **Response**: `view()`, `redirect()->route()`, `->with()` para flash,
  `->withErrors()`, `->withInput()`, `back()`, descargas y streaming de
  archivos.
- **Middleware**: un solo pipeline para global y por ruta, alias (`'auth'`) y
  grupos (`'web'`, `'api'`).
- **Errores**: `abort(404)` lanza `HttpException` en vez de hacer `exit`;
  `ExceptionHandler` decide entre HTML (vista de `errors/`), JSON o página de
  depuración según `APP_DEBUG` y el tipo de petición.

### 4.3 Vistas y componentes

Es la pieza más visible del framework, así que se especifica con detalle.

**Sintaxis de vistas.** Archivos `.php` normales con un conjunto pequeño y
cerrado de extensiones. Todo lo que no esté en la lista se escribe en PHP
plano.

| Sintaxis                                     | Compila a                                         |
| -------------------------------------------- | ------------------------------------------------- |
| `{{ $expr }}`                                | `<?= e($expr) ?>` (siempre escapado)              |
| `{!! $expr !!}`                              | `<?= $expr ?>` (sin escapar, uso explícito)       |
| `@if / @elseif / @else / @endif`             | Estructuras `if` de PHP                           |
| `@foreach / @empty / @endforeach`            | `foreach` con rama vacía opcional                 |
| `@csrf`, `@method('PUT')`                    | Campos ocultos del formulario                     |
| `@error('campo') ... @enderror`              | Bloque con `$message` si el campo tiene error     |
| `@props(['title' => null])`                  | Declaración de props del componente               |
| `<x-nombre attr="texto" :attr="$expr">`      | Render del componente `components/nombre.php`     |
| `<x-slot:footer> ... </x-slot:footer>`       | Slot con nombre                                   |
| `<x-layouts.app>`                            | Componente en subcarpeta (`components/layouts/`)  |

**Ejemplo de página:**

```php
<x-layouts.app title="Tareas">
    <x-card title="Pendientes">
        @foreach ($tasks as $task)
            <x-task-item :task="$task" />
        @empty
            <p class="text-gray-500">No hay tareas todavía.</p>
        @endforeach

        <x-slot:footer>
            <x-button href="{{ route('tasks.create') }}">Nueva tarea</x-button>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
```

**Ejemplo de componente** (`resources/views/components/card.php`):

```php
@props(['title' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl bg-white p-6 shadow']) }}>
    @if ($title)
        <h2 class="mb-4 text-lg font-semibold">{{ $title }}</h2>
    @endif
    {{ $slot }}
    @if (isset($footer))
        <div class="mt-4 border-t pt-4">{{ $footer }}</div>
    @endif
</div>
```

**Reglas del motor:**

- Compilación a `storage/cache/views/` con invalidación por `filemtime`.
- `bootzen view:cache` precompila todo para producción; `view:clear` limpia.
- Componentes anónimos (solo vista) por defecto. Si un componente necesita
  lógica, se crea `App\View\Components\Nombre` con método `data()` y se
  resuelve automáticamente.
- Bolsa de atributos (`$attributes`) con `merge()` y `class()` para combinar
  clases de Tailwind.
- Datos compartidos (`View::share()`) para usuario actual, flash y errores.
- Mensajes de error del compilador con archivo y línea de la vista original,
  no del archivo compilado.
- El compilador se mantiene pequeño (meta: menos de 600 líneas) y con tests
  de snapshot por cada directiva.

**Migración de componentes actuales.** Alert, Button, Card, Form, Input,
Layout y Navigation pasan de clases PHP a `resources/views/components/*.php`
dentro del skeleton. `Component.php` y `CacheTenant.php` se eliminan.

### 4.4 Frontend: CSS y JavaScript

**CSS**

- Tailwind v4 con configuración en CSS (`@import "tailwindcss"; @source
  "../views";`) y los plugins oficiales de forms y typography.
- `bin/bootzen css:watch` y `css:build` descargan y usan el binario standalone
  en `storage/bin/` (sin Node), verificando su SHA-256 contra el release. Si el proyecto tiene `package.json`, se usa
  `npx` en su lugar.
- El CSS compilado se genera en `public/assets/app.css` y se incluye en el
  artefacto de despliegue, así que el servidor nunca compila nada.

**JavaScript**

- Módulos ES nativos directamente en `public/assets/js/` (sin copia ni
  transformación: lo que se escribe es lo que se sirve).
- Runtime propio `bootzen.js` (meta: ~3 KB) con:
  - `data-component="dropdown"`: monta automáticamente
    `components/dropdown.js` (islas de interactividad).
  - `fetch` con cabecera CSRF y manejo de JSON y errores de validación.
  - Mejoras progresivas para formularios: `data-confirm`, deshabilitar al
    enviar, envío por fetch con `data-remote`.
  - Mensajes flash como toasts.
- Helper `asset('app.css')` con versión por hash o `filemtime` para invalidar
  caché del navegador.
- Sin Vite, sin HMR, sin bundler. Si un proyecto lo necesita, lo agrega.

### 4.5 Datos

- **Connection** sobre PDO. SQLite por defecto con `PRAGMA journal_mode=WAL`,
  `foreign_keys=ON`, `busy_timeout=5000` y `synchronous=NORMAL`. MySQL soportado
  pero los tests se centran en SQLite.
- **QueryBuilder** mínimo: `select`, `where` (y `whereIn`, `whereNull`),
  `orderBy`, `limit`, `join`, `insert`, `update`, `delete`, `count`,
  `paginate`, `transaction`. Siempre con parámetros enlazados.
- **Model** ligero (Active Record): `find`, `findOrFail`, `where`, `create`,
  `save`, `delete`, `$fillable`, `$casts` (int, bool, json, datetime),
  timestamps, relaciones `hasMany` y `belongsTo`. Sin eventos, sin scopes
  globales, sin magia adicional.
- **Migraciones** en PHP con **SQL directo** (`up()` y `down()` reciben la
  conexión). No hay Schema Builder: la IA escribe SQL correcto y nos ahorramos
  mantener una capa entera. Tabla `migrations` para el control.
- **Seeders** en PHP y factories simples con datos falsos opcionales (Faker
  solo en dev).
- `bootzen db:backup` con `VACUUM INTO` para copias consistentes en caliente.

### 4.6 Sesión, seguridad y autenticación

- **Session** sobre sesiones nativas de PHP guardadas en `storage/sessions`,
  con flash, `old()` y regeneración de ID en login. Cookies `HttpOnly`,
  `SameSite=Lax` y `Secure` en HTTPS.
- **CSRF** como middleware del grupo `web`, token en `_token` o cabecera
  `X-CSRF-TOKEN`, respuesta 419 con su vista.
- **Validator** con reglas: `required`, `nullable`, `string`, `integer`,
  `numeric`, `boolean`, `email`, `url`, `min`, `max`, `between`, `in`,
  `confirmed`, `date`, `regex`, `unique`, `exists`, `file`, `image`, `mimes`,
  arrays con `items.*.campo`. Mensajes en español por defecto.
- **Auth** con guard de sesión, `password_hash` (Argon2id si está disponible),
  middleware `auth` y `guest`, y throttling de login.
- **Auth scaffold opcional** (`bootzen new mi-app --auth`): registro, login,
  logout y recuperación de contraseña con sus vistas.
- **SecurityHeaders** middleware (nosniff, frame options, referrer policy, HSTS
  en HTTPS, CSP básica configurable).
- **RateLimit** middleware con almacenamiento en cache de archivo o SQLite.

### 4.7 CLI (`bin/bootzen`)

Un único punto de entrada en PHP para todo. Reemplaza `make.sh`, `start.sh`,
`analyzer.sh` y `count_lines.sh`.

| Comando                                  | Acción                                                            |
| ---------------------------------------- | ----------------------------------------------------------------- |
| `serve`                                  | Servidor embebido + watcher de Tailwind en paralelo               |
| `make:controller/model/middleware/...`   | Generadores individuales                                          |
| `make:component nombre`                  | Componente `<x-*>` (con `--class` si necesita lógica)             |
| `make:resource Post title:string ...`    | Rebanada vertical completa (ver sección 5)                        |
| `migrate`, `migrate:rollback`, `status`  | Migraciones                                                       |
| `db:seed`, `db:backup`, `db:fresh`       | Datos                                                             |
| `route:list`                             | Tabla de rutas con nombre y middleware                            |
| `view:cache`, `view:clear`               | Caché de vistas                                                   |
| `config:cache`, `route:cache`            | Cachés de producción                                              |
| `key:generate`                           | `APP_KEY`                                                         |
| `css:build`, `css:watch`                 | Tailwind standalone                                               |
| `doctor`                                 | Verifica versión de PHP, extensiones, permisos y `.env`           |
| `check`                                  | Formato, PHPStan y tests en un solo comando                       |
| `deploy:build`                           | Genera el artefacto listo para subir (ver sección 6)              |
| `framework:update`                       | Actualiza `framework/` desde el release más reciente              |

### 4.8 Testing para proyectos

- Pest con un `TestCase` que permite `$this->get('/tasks')`,
  `->post()`, `->actingAs($user)`, `->assertSee()`, `->assertRedirect()`,
  `->assertSessionHasErrors()`, `->assertDatabaseHas()`.
- SQLite en memoria por test, migraciones automáticas.
- Esto es lo que le da a la IA un ciclo de verificación real: genera, corre
  `bootzen check`, corrige.

---

## 5. Capa para IA generativa

El objetivo no es "tener un archivo para la IA", sino que el código y las
herramientas empujen a los agentes a hacer las cosas de una sola forma.

### 5.1 Principios de diseño

1. **Una forma de hacer cada cosa.** Sin alternativas equivalentes (nada de
   tres formatos de handler si basta con uno recomendado).
2. **Explícito antes que mágico.** Sin facades estáticas ni métodos mágicos
   difíciles de rastrear. Si la IA puede seguir la llamada con "ir a
   definición", acierta más.
3. **Tipos estrictos y PHPStan nivel 8** en framework y skeleton, para que los
   errores de la IA salgan antes de ejecutar.
4. **Errores que explican qué hacer.** "Vista `tasks.index` no encontrada en
   `resources/views/tasks/index.php`" en vez de un warning genérico.
5. **Archivos pequeños y con nombre predecible.** La IA encuentra todo por
   convención.

### 5.2 Entregables

- **`AGENTS.md` en el skeleton** (con `CLAUDE.md` que lo importa): stack,
  mapa de carpetas, convenciones, comandos y una lista de "no hagas esto"
  (no instalar dependencias de runtime, no escribir SQL concatenado, no poner
  HTML en controladores).
- **Recetas** en `docs/ai/recipes/`: crear un CRUD, agregar un componente,
  proteger rutas, agregar un endpoint JSON, agregar una migración, subir
  archivos, enviar un correo. Cada receta con pasos y archivos a tocar.
- **Referencia compacta** en `docs/ai/reference.md` (y `llms.txt`): la API
  pública de cada módulo en formato denso, generada a partir de los PHPDoc
  para que no se desincronice.
- **Skills** en `.claude/skills/` y `.agents/skills/` del skeleton:
  `crear-recurso`, `crear-componente`, `revisar-seguridad`.
- **Generador de rebanada vertical**: `bootzen make:resource Task
  title:string done:bool` crea migración, modelo, controlador con validación,
  rutas `resource`, vistas index/create/edit/show con componentes y tests de
  feature. Humanos e IA usan el mismo camino, y el resultado sirve de ejemplo
  vivo del estilo esperado.
- **App de demostración** (`bootzen new demo --demo`): auth + CRUD de tareas,
  como referencia que la IA puede imitar.

### 5.3 Prueba de aceptación de la capa IA

Con un proyecto recién creado y solo el `AGENTS.md` como contexto, un agente
debe poder completar estos pedidos sin intervención y con `bootzen check` en
verde:

1. "Agrega un CRUD de clientes con nombre, email y teléfono."
2. "Protege el CRUD para que solo usuarios logueados lo vean."
3. "Agrega un endpoint JSON que liste los clientes con paginación."
4. "Crea un componente de modal y úsalo para confirmar el borrado."

---

## 6. Despliegue

El mismo artefacto sirve para los tres escenarios. `bootzen deploy:build`
genera `build/mi-app-<versión>.zip` con:

- Código de `app/`, `framework/`, `config/`, `routes/`, `resources/views/`,
  `public/`, `database/migrations/` y `bin/bootzen`.
- CSS compilado, vistas precompiladas, config y rutas cacheadas.
- Sin `tests/`, `node_modules/`, `vendor/` de desarrollo ni `.env`.

### 6.1 Hosting compartido

- Subir el zip por FTP o el administrador de archivos y descomprimir.
- Si el hosting permite elegir document root, apuntarlo a `public/`. Si no,
  un `.htaccess` en la raíz redirige todo a `public/` y bloquea el acceso al
  resto.
- `bin/bootzen migrate` por SSH o, si no hay SSH, una ruta de instalación
  protegida por `APP_KEY` que se desactiva sola después de usarse.
- Tareas programadas con un cron que ejecuta `bin/bootzen schedule:run`.

### 6.2 VPS

- Configuraciones de ejemplo para **Caddy** (HTTPS automático) y **Nginx +
  PHP-FPM** en `docker/` y `docs/deploy/`.
- Script `deploy.sh` con rsync, `migrate --force` y recarga de PHP-FPM.
- Unidad systemd opcional para el worker de jobs.

### 6.3 Docker

- `Dockerfile` basado en la imagen oficial `php:8.3-apache` (mod_rewrite y
  document root en `public/`), con OPcache activado.
- `docker-compose.yml` con volúmenes para `database/` y `storage/` para que
  SQLite y los uploads sobrevivan al contenedor.
- FrankenPHP documentado como alternativa (un binario, HTTPS automático).

### 6.4 Comunes a todos

- Endpoint `/up` para health checks.
- `bootzen doctor` antes del primer despliegue.
- Base de datos SQLite siempre fuera de `public/` y con permisos correctos.
- Guía de backups (`db:backup` + cron) y límites conocidos de SQLite
  (escrituras concurrentes intensas: momento de pasar a MySQL).

---

## 7. Fases de implementación

Cada fase termina con algo usable y verificable. El esfuerzo es relativo
(S, M, L) porque el ritmo real depende del tiempo disponible.

### Fase 0: Limpieza y reorganización (M)

- Crear `composer.json` en la raíz (solo dev) y `tests/` con Pest.
- Mover `core/src` a `framework/src` y crear `framework/bootstrap.php` con
  autoloader propio.
- Crear `skeleton/` con los archivos que hoy genera `init_project.sh`,
  corrigiendo namespaces (`App\`) y scripts.
- Eliminar del framework: `HomeController`, `ApiRoutes`, `Cache` Redis,
  `CacheTenant`, polyfills de aleatorios, `exit` en helpers.
- Retirar el `.phar`: `build-phar.php`, `core/stub.php` y el paso de release.
  El release pasa a publicar un tarball de `framework/` + `skeleton/`.
- Workflow `ci.yml`: PHPStan nivel 8 y tests en PHP 8.2, 8.3 y 8.4.
- **Criterio**: `bin/bootzen-new demo` crea un proyecto que responde "Hola" en
  `php -S`, sin Composer en runtime, y CI en verde.

### Fase 1: Núcleo HTTP sólido (M)

- `Env`, `Config`, `Logger`, `Cache` de archivo.
- Router reescrito (compilación, nombres, 405, grupos, `resource`).
- Request y Response ampliados; `HttpException` y `ExceptionHandler`.
- Pipeline de middleware unificado con alias y grupos.
- Tests de las piezas críticas con cobertura mínima de 90%.
- **Criterio**: rutas con nombre, parámetros con restricciones y páginas de
  error propias funcionando con tests.

### Fase 2: Vistas, componentes y frontend (L)

- Compilador de vistas con la sintaxis de la sección 4.3.
- Componentes anónimos y con clase, slots, `$attributes`.
- Migrar los 7 componentes actuales a vistas `<x-*>` con Tailwind v4.
- Layout base, vistas de error, `asset()`.
- Tailwind standalone (`css:build`, `css:watch`) y runtime `bootzen.js`.
- **Criterio**: una página con layout, componentes anidados, slots con nombre
  y un componente JS (`data-component`) funcionando, con snapshots en tests.

### Fase 3: Datos (M)

- Connection SQLite/MySQL, QueryBuilder, Model, migraciones SQL, seeders.
- Comandos `migrate`, `db:seed`, `db:fresh`, `db:backup`.
- **Criterio**: CRUD completo contra SQLite con tests en memoria.

### Fase 4: Sesión, seguridad y auth (M)

- Session, flash, `old()`, CSRF, Validator, SecurityHeaders, RateLimit.
- Auth con guard de sesión y scaffold opcional `--auth`.
- **Criterio**: registro, login, logout y formulario con errores de
  validación funcionando de punta a punta; auditoría con el checklist de
  `docs/PROCESS.md`.

### Fase 5: CLI y generadores (M)

- `bin/bootzen` con todos los comandos de la sección 4.7.
- `make:resource` generando la rebanada vertical con tests.
- `TestCase` de proyecto con helpers HTTP y de base de datos.
- **Criterio**: `make:resource Task title:string done:bool` produce un CRUD
  que pasa `bootzen check` sin tocar nada.

### Fase 6: Capa IA (M)

- `AGENTS.md`, `CLAUDE.md`, recetas, referencia compacta, `llms.txt`, skills.
- App de demostración.
- **Criterio**: los cuatro pedidos de la sección 5.3 completados por un
  agente con `bootzen check` en verde.

> Nota: `AGENTS.md` del skeleton se empieza en la Fase 0 y se actualiza al
> cerrar cada fase. La Fase 6 es la de pulido y validación, no la primera vez
> que se escribe.

### Fase 7: Despliegue y release v2.0.0 (M)

- `deploy:build`, configs de Docker, Caddy, Nginx y `.htaccess`.
- Guías de hosting compartido, VPS y Docker probadas de verdad.
- `framework:update`, CHANGELOG y guía de migración desde v1.
- **Criterio**: la app demo desplegada en los tres escenarios siguiendo solo
  la documentación.

### Fase 8: Extras opcionales (post v2.0)

En orden de utilidad para un MVP:

1. Mail (driver `log` y SMTP mínimo propio).
2. Jobs con cola en SQLite y `queue:work --stop-when-empty` para cron.
3. Subida de archivos con validación y almacenamiento local.
4. Scheduler (`schedule:run`).
5. i18n básico.

### Dependencias entre fases

```
Fase 0 → Fase 1 → Fase 2 ─┐
              └─→ Fase 3 ─┼→ Fase 4 → Fase 5 → Fase 6 → Fase 7
                          │
               (2 y 3 pueden ir en paralelo)
```

---

## 8. Qué se descarta

| Elemento                                  | Motivo                                                      |
| ----------------------------------------- | ----------------------------------------------------------- |
| Distribución en `.phar`                   | Opaco para la IA y frágil en hostings compartidos           |
| Arquitectura de plugins Composer          | Complejidad que un MVP monolítico no necesita               |
| Redis, Predis, Monolog, phpdotenv         | Dependencias de runtime reemplazables con poco código       |
| Schema Builder                            | SQL directo es más simple y la IA lo escribe bien           |
| JWT, WebSockets, Vite/HMR                 | Fuera del alcance de un MVP; se agregan por proyecto        |
| PostgreSQL como objetivo de pruebas       | PDO lo soporta, pero no se garantiza en v2.0                |
| Componentes como clases que devuelven HTML| Reemplazados por componentes de vista `<x-*>`               |

---

## 9. Riesgos y cómo cubrirlos

| Riesgo                                                    | Mitigación                                                                                 |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| El compilador de vistas crece sin control                 | Lista cerrada de directivas, límite de tamaño, tests de snapshot por directiva             |
| SQLite se queda corto en escritura concurrente            | WAL + `busy_timeout`, documentar el límite y que el cambio a MySQL sea solo de `.env`      |
| Hostings sin SSH para migraciones                         | Ruta de instalación protegida por `APP_KEY` que se autodesactiva                           |
| Binario de Tailwind no disponible para alguna plataforma  | Fallback a `npx @tailwindcss/cli` y CSS compilado siempre incluido en el artefacto         |
| Actualizar `framework/` en proyectos existentes           | `framework:update` con verificación de versión y aviso si hay cambios locales en la carpeta|
| Ruptura con v1                                            | v1 es preliminar según el README; guía de migración corta en la Fase 7                     |

---

## 10. Primeros pasos concretos

Los primeros cinco PRs, en orden:

1. `chore(repo)`: `composer.json` de dev, Pest, PHPStan y `ci.yml`.
2. `refactor(core)`: mover `core/src` a `framework/src`, autoloader propio,
   eliminar ejemplos, Redis y polyfills.
3. `feat(skeleton)`: carpeta `skeleton/` + `bin/bootzen-new`, con
   `init_project.sh` como envoltorio.
4. `fix(router)`: compilación de rutas, parámetros en el request, 405 y rutas
   con nombre, con tests.
5. `feat(http)`: `HttpException`, `ExceptionHandler` y vistas de error, con
   `abort()` sin `exit`.

Con esos cinco ya hay un proyecto que arranca, tiene CI y una base testeada
sobre la que construir vistas y datos.
