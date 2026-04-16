# Plan de Desarrollo BootZen Framework

> Objetivo: Evolucionar BootZen de un motor de componentes + router a un
> **microframework MVC profesional** para proyectos monolíticos PHP, manteniendo
> la filosofía de simplicidad y la regla del 80% (si lo necesitas en el 80% de
> los proyectos → core; si no → módulo/plugin).

---

## Estado Actual (v1.1.0)

### Lo que YA funciona

| Área                           | Estado | Detalle                                                                      |
| ------------------------------ | ------ | ---------------------------------------------------------------------------- |
| Application (Front Controller) | ✅     | Ciclo de vida, error handling, debug mode                                    |
| Router                         | ✅     | GET/POST/PUT/PATCH/DELETE, parámetros dinámicos, grupos, middleware por ruta |
| Request                        | ✅     | Value object inmutable, detección JSON/AJAX/HTTPS, IP con proxy              |
| Response                       | ✅     | Factories (json/html/redirect/errores), cookies SameSite, inmutable          |
| Container (IoC)                | ✅     | bind/singleton/make, auto-resolución por reflexión                           |
| Middleware pipeline            | ✅     | Cadena recursiva, global y por ruta                                          |
| CorsMiddleware                 | ✅     | Configurable, preflight OPTIONS                                              |
| Componentes UI                 | ✅     | Alert, Button, Card, Form, Input, Layout, Navigation con Tailwind            |
| Cache (Redis)                  | ✅     | Predis, multi-tenant, remember, flush                                        |
| Helpers                        | ✅     | 20+ funciones (dd, env, url, e, csrf helpers, slugify, etc.)                 |
| Distribución Phar              | ✅     | build-phar.php + CI/CD GitHub Actions                                        |
| Scaffolding                    | ✅     | init_project.sh genera proyecto completo                                     |
| Generador de código            | ✅     | make.sh crea controllers, models, services, migrations, tests                |

### Lo que NO existe (brechas críticas)

| Área                                      | Impacto                        | Prioridad |
| ----------------------------------------- | ------------------------------ | --------- |
| Base de datos / ORM / Query Builder       | **Bloqueante** para MVC        | 🔴 Alta   |
| Sistema de Vistas (templates en archivos) | **Bloqueante** para MVC        | 🔴 Alta   |
| Gestión de Sesiones                       | **Bloqueante** para auth/forms | 🔴 Alta   |
| Middleware CSRF                           | Seguridad crítica              | 🔴 Alta   |
| Validación de datos                       | UX y seguridad                 | 🔴 Alta   |
| Autenticación / Autorización              | Proyectos reales               | 🟡 Media  |
| Configuración por archivos                | Organización                   | 🟡 Media  |
| Sistema de Eventos                        | Extensibilidad                 | 🟡 Media  |
| Logging integrado (Monolog)               | Observabilidad                 | 🟡 Media  |
| Tests del framework                       | Calidad                        | 🟡 Media  |
| CLI (comandos tipo artisan)               | DX                             | 🟢 Baja   |
| Queue / Jobs                              | Escalabilidad                  | 🟢 Baja   |
| Mail                                      | Comunicación                   | 🟢 Baja   |
| Internacionalización (i18n)               | Alcance                        | 🟢 Baja   |
| WebSockets                                | Tiempo real                    | 🟢 Baja   |
| JWT                                       | APIs avanzadas                 | 🟢 Baja   |

---

## Fases de Desarrollo

### Fase 1 — Cimientos MVC (Semanas 1-4)

> **Meta**: Que BootZen pueda funcionar como un framework MVC básico. Un usuario
> puede crear un proyecto, conectarse a una BD, renderizar vistas desde
> archivos, y tener un formulario funcional con protección CSRF.

#### 1.1 Sistema de Configuración por Archivos

- Crear `Config.php` en `core/src/Core/`
- Soporte para archivos PHP que retornan arrays (`config/app.php`,
  `config/database.php`, `config/cache.php`)
- Merge con variables de entorno (.env) — la config de archivos es default, .env
  sobreescribe
- Cacheo de configuración en producción
- Helper `config('database.default')` con dot notation

```
config/
  app.php          # APP_NAME, APP_ENV, APP_DEBUG, APP_URL, APP_TIMEZONE
  database.php     # Conexiones default, mysql, sqlite, pgsql
  cache.php        # Driver default (file, redis), ttl por defecto
  session.php      # Driver (file, cookie), lifetime, encryption
  cors.php         # Orígenes permitidos, métodos, headers
  logging.php      # Canales, niveles, formato
```

#### 1.2 Sistema de Vistas (View Engine)

- Crear `View.php` en `core/src/Core/`
- Renderiza archivos `.php` desde un directorio configurable (`views/`)
- Layouts con `@extends('layouts.app')` y `@yield('content')`
- Includes/partials con `@include('partials.header')`
- Datos compartidos (`with()`, `share()` global)
- Componentes Blade-lite opcionales
  (`@component('alert', ['type' => 'success'])`)
- Output buffering para capturar y retornar contenido
- Integración con Component.php existente (los componentes UI pueden usarse
  dentro de vistas)

```php
// Uso
return view('home.index', ['title' => 'Inicio', 'projects' => $projects]);
return view('dashboard')->with('stats', $stats)->layout('layouts.dashboard');
```

#### 1.3 Capa de Base de Datos

**Connection.php** — Gestión de conexiones PDO

- Pool de conexiones múltiples (default, mysql, sqlite, pgsql)
- Configuración desde `config/database.php`
- Reconnect automático
- Logging de queries en debug mode
- Prepared statements por defecto

**QueryBuilder.php** — Query builder fluido

- `select()`, `where()`, `orderBy()`, `groupBy()`, `limit()`, `offset()`
- `join()`, `leftJoin()`, `rightJoin()`
- `insert()`, `update()`, `delete()`
- Agregados: `count()`, `sum()`, `avg()`, `max()`, `min()`
- Paginación: `paginate(page, perPage)`
- Raw queries seguras cuando sea necesario

```php
$users = DB::table('users')
    ->select('id', 'name', 'email')
    ->where('active', true)
    ->orderBy('name')
    ->limit(10)
    ->get();

DB::table('users')->insert(['name' => 'Juan', 'email' => 'juan@test.com']);
```

**Model.php** — ORM base activo

- Mapeo a tabla (`protected string $table`)
- Campos fillable / guarded
- Casts automáticos (int, bool, json, datetime)
- CRUD: `find()`, `findOrFail()`, `create()`, `update()`, `delete()`
- Relaciones básicas: `hasMany()`, `belongsTo()`, `hasOne()`
- Scopes: `scopeActive()`, `scopeRecent()`
- Hooks: `creating()`, `created()`, `updating()`, `updated()`, `deleting()`,
  `deleted()`
- Serialización: `toArray()`, `toJson()`

```php
class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $casts = ['active' => 'bool', 'meta' => 'json'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

$user = User::find(1);
$user->posts; // Lazy loading
```

**Migration.php** — Sistema de migraciones

- Archivos numerados timestamp (`20260415_000001_create_users_table.php`)
- Métodos `up()` / `down()` con Schema Builder
- Schema Builder: `createTable()`, `dropTable()`, `addColumn()`, `dropColumn()`
- Tipos de columna: `increments`, `string`, `text`, `integer`, `boolean`,
  `timestamp`, `json`, `foreign`
- CLI: `php .scripts/migrate.php` para ejecutar/rollback migraciones
- Tabla de seguimiento `migrations` para registrar ejecutadas

```php
class CreateUsersTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->createTable('users', function ($table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('users');
    }
}
```

**Seeder.php** — Datos de prueba

- `run()` con inserciones masivas
- Factory helper para datos aleatorios básicos
- CLI: `php .scripts/seed.php`

#### 1.4 Gestión de Sesiones

- Crear `Session.php` en `core/src/Core/`
- Driver file (default) y cookie
- `get()`, `set()`, `has()`, `remove()`, `flush()`, `flash()`, `reflash()`
- Regeneración de ID para prevenir fixation
- Configurable desde `config/session.php`
- Integración con helpers existentes (`old()`, `csrf_token()`)

#### 1.5 Middleware CSRF

- Crear `CsrfMiddleware.php` en `core/src/Middleware/`
- Token generado por sesión, validado en POST/PUT/PATCH/DELETE
- Exclusiones configurables (rutas API, webhooks)
- Token en header `X-CSRF-TOKEN` o campo `_token` en body
- Integrar con `Form.php` component existente
- Helper `csrf_token()` ya existe, verificar que funcione con nueva Session

#### 1.6 Validación de Datos

- Crear `Validator.php` en `core/src/Core/`
- Reglas: `required`, `string`, `integer`, `numeric`, `email`, `url`, `min`,
  `max`, `between`, `in`, `not_in`, `regex`, `confirmed`, `unique`, `exists`,
  `array`, `boolean`, `date`, `alpha`, `alpha_num`, `alpha_dash`
- Validación anidada (`user.name`, `items.*.price`)
- Mensajes de error personalizables
- Integración con Request:
  `$request->validate(['name' => 'required|string|max:100'])`
- Retorna errores a la vista anterior con inputs antiguos (flash session)

```php
// En un controller
$errors = $request->validate([
    'name'     => 'required|string|max:100',
    'email'    => 'required|email|unique:users',
    'password' => 'required|min:8|confirmed',
]);

if ($errors) {
    return redirect('/register')->with('errors', $errors)->withInput();
}
```

#### 1.7 Actualizar init_project.sh y templates

- Actualizar `composer.json` del scaffold: quitar dependencias no usadas,
  ajustar autoload
- Crear archivos de config por defecto en el scaffold
- Actualizar templates de `make.sh` para usar las nuevas clases reales
- Crear migración de ejemplo (`CreateUsersTable`)
- Crear seeder de ejemplo (`UserSeeder`)
- Crear vista de ejemplo (`home/index.php` con layout)

#### Criterios de aceptación Fase 1

- [ ] Crear proyecto con `init_project.sh`, ejecutar migración, ver vista
      renderizada desde archivo
- [ ] Formulario POST con CSRF que valida datos y muestra errores
- [ ] Conexión a MySQL/SQLite funcional con query builder
- [ ] Modelo CRUD básico funcional (create, read, update, delete)
- [ ] Sesiones flash para errores de validación y old input

---

### Fase 2 — Autenticación y Seguridad (Semanas 5-7)

> **Meta**: Proyectos con login funcional, protección de rutas y seguridad
> básica robusta.

#### 2.1 Sistema de Autenticación

**Auth.php** — Gestor de autenticación

- `attempt($credentials)` — login con credenciales
- `login($user)` — login directo de usuario
- `logout()` — cerrar sesión
- `check()` — ¿está autenticado?
- `guest()` — ¿es invitado?
- `user()` — usuario actual
- `id()` — ID del usuario actual
- Hash con `password_hash()` (bcrypt por defecto, argon2 si está disponible)
- Configurable: modelo de usuario, campo email/username, campo password

**SessionGuard.php** — Guard de sesión

- Almacena user_id en sesión
- Carga usuario desde BD en cada request
- Regenera session ID al hacer login

**AuthMiddleware.php** — Middleware de autenticación

- Protege rutas que requieren login
- Redirige a login o retorna 401 (API)
- Configurable por ruta o grupo

**GuestMiddleware.php** — Middleware para invitados

- Redirige a dashboard si ya está autenticado
- Útil para páginas de login/registro

```php
// Rutas protegidas
$router->group(['prefix' => '/dashboard', 'middleware' => [AuthMiddleware::class]], function ($router) {
    $router->get('/', [DashboardController::class, 'index']);
    $router->get('/profile', [ProfileController::class, 'edit']);
    $router->post('/profile', [ProfileController::class, 'update']);
});

// Login/logout
$router->get('/login', [AuthController::class, 'showLogin'])->middleware(GuestMiddleware::class);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'])->middleware(AuthMiddleware::class);
```

**AuthController** — Controller base de autenticación

- Login con validación
- Registro opcional
- Logout
- Protección contra brute force (rate limiting básico)

#### 2.2 Middleware de Rate Limiting

- Crear `RateLimitMiddleware.php`
- Contador por IP en sesión o cache
- Configurable: intentos máximos, ventana de tiempo
- Retorna 429 Too Many Requests
- Integrar con login para prevenir brute force

#### 2.3 Headers de Seguridad

- Crear `SecurityHeadersMiddleware.php`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY` o `SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` (solo HTTPS)
- `Content-Security-Policy` básico configurable

#### 2.4 Sanitización de Inputs

- Crear `Sanitizer.php` o integrar en Request
- `trim()`, `strip_tags()`, `htmlspecialchars()` configurable
- Sanitización automática opcional en Request
- Whitelist/blacklist de campos

#### Criterios de aceptación Fase 2

- [ ] Login/logout funcional con sesiones
- [ ] Rutas protegidas redirigen a login
- [ ] Rate limiting en endpoint de login (ej: 5 intentos / 15 min)
- [ ] Headers de seguridad presentes en respuestas
- [ ] Inputs sanitizados contra XSS básico

---

### Fase 3 — Calidad y Observabilidad (Semanas 8-10)

> **Meta**: Framework testeable, con logging profesional y herramientas de
> desarrollo que aumenten la productividad.

#### 3.1 Logging Integrado

- Integrar Monolog (ya es dependencia en composer.json del scaffold)
- Crear `LogManager.php` en `core/src/Core/`
- Canales configurables desde `config/logging.php`: `stack`, `single`, `daily`,
  `slack`
- Niveles PSR-3: debug, info, notice, warning, error, critical, alert, emergency
- Helper `log()`, `log_info()`, `log_error()`, `log_debug()`
- Context automático: request URI, method, IP, user_id si autenticado
- Reemplazar el `logger()` helper actual con integración real

```php
config/logging.php:
[
    'default' => 'stack',
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => ['single', 'slack']],
        'single' => ['driver' => 'single', 'path' => 'storage/logs/app.log', 'level' => 'debug'],
        'daily' => ['driver' => 'daily', 'path' => 'storage/logs/app.log', 'days' => 14],
    ],
]
```

#### 3.2 Tests del Framework

**Estructura:**

```
tests/
  Unit/
    Core/
      ApplicationTest.php
      RouterTest.php
      RequestTest.php
      ResponseTest.php
      ContainerTest.php
      ConfigTest.php
      SessionTest.php
      ValidatorTest.php
      ViewTest.php
      QueryBuilderTest.php
      ModelTest.php
    Components/
      AlertTest.php
      ButtonTest.php
      CardTest.php
      FormTest.php
      InputTest.php
      LayoutTest.php
      NavigationTest.php
    Middleware/
      CorsMiddlewareTest.php
      CsrfMiddlewareTest.php
      AuthMiddlewareTest.php
    Helpers/
      HelpersTest.php
  Feature/
    RoutingTest.php        # Requests completos a rutas
    AuthFlowTest.php       # Login/logout/protected routes
    FormSubmissionTest.php # POST con CSRF + validación
    CrudTest.php           # Operaciones CRUD con modelo
```

- PHPUnit + Pest (configurar `phpunit.xml` en raíz del repo)
- Mock de Request/Response para tests unitarios
- Base de datos SQLite en memoria para tests de integración
- Coverage mínimo objetivo: 70%
- CI: ejecutar tests en GitHub Actions antes de build del phar

#### 3.3 Middleware de Error/Exception Mejorado

- Mejorar `ErrorHandler` existente
- Página de error personalizable en producción (views/errors/404.php, 500.php)
- JSON error response para requests API
- Log automático de excepciones no capturadas
- Whoops-like en desarrollo (stack trace formateado)
- Errores 404, 403, 419 (CSRF), 429 (rate limit), 500 con vistas propias

#### 3.4 Request Lifecycle Events

- Crear `EventDispatcher.php` en `core/src/Core/`
- Eventos del ciclo de vida: `request.received`, `response.sending`,
  `exception.thrown`
- Eventos de autenticación: `user.login`, `user.logout`, `user.registered`
- Eventos de modelo: `model.creating`, `model.created`, `model.updating`, etc.
- Listener registration simple: `$app->on('user.login', fn($user) => ...)`
- Sin necesidad de un bus de eventos pesado

```php
// Registrar listeners
$app->on('user.login', function ($user) {
    log_info("Usuario {$user->email} inició sesión");
});

// Disparar eventos (desde Auth, Model, etc.)
$app->dispatch('user.login', $user);
```

#### 3.5 Generador de Documentación

- Extender `analyzer.sh` o crear `docs.sh`
- Generar documentación de API desde PHPDoc
- Generar rutas documentadas automáticamente
- Ejemplos de uso de cada componente

#### Criterios de aceptación Fase 3

- [ ] Logs aparecen en `storage/logs/` con formato legible
- [ ] Tests unitarios corren con `composer test` y pasan
- [ ] Página 404 personalizada renderiza en lugar de error crudo
- [ ] Evento `user.login` dispara listener correctamente
- [ ] Error en producción muestra página amigable, en desarrollo muestra stack
      trace

---

### Fase 4 — DX y Productividad (Semanas 11-13)

> **Meta**: Que desarrollar con BootZen sea cómodo. Generadores, CLI y
> convenciones que reduzcan fricción.

#### 4.1 CLI — BootZen Commands

- Crear `bin/bootzen` (script PHP ejecutable) o extender `.scripts/`
- Comandos:

| Comando                              | Acción                                      |
| ------------------------------------ | ------------------------------------------- |
| `bootzen serve`                      | Levanta servidor de desarrollo              |
| `bootzen make:controller Name`       | Genera controller con CRUD básico           |
| `bootzen make:model Name`            | Genera modelo con fillable/casts/relaciones |
| `bootzen make:middleware Name`       | Genera middleware con interface             |
| `bootzen make:service Name`          | Genera service class                        |
| `bootzen make:view name`             | Genera vista con layout base                |
| `bootzen make:migration description` | Genera archivo de migración                 |
| `bootzen make:seeder Name`           | Genera seeder                               |
| `bootzen make:test Name`             | Genera test unitario/feature                |
| `bootzen migrate`                    | Ejecuta migraciones pendientes              |
| `bootzen migrate:rollback`           | Revierte última migración                   |
| `bootzen migrate:status`             | Lista migraciones ejecutadas                |
| `bootzen seed`                       | Ejecuta seeders                             |
| `bootzen db:wipe`                    | Borra todas las tablas                      |
| `bootzen route:list`                 | Lista todas las rutas registradas           |
| `bootzen cache:clear`                | Limpia cache de configuración/views         |
| `bootzen config:cache`               | Cachea configuración para producción        |
| `bootzen key:generate`               | Genera APP_KEY                              |
| `bootzen storage:link`               | Crea symlink de storage                     |

- Reescribir `make.sh` y integrarlo en CLI unificado
- Output con colores (ANSI codes) y emojis opcionales

#### 4.2 Service Providers

- Crear `ServiceProvider.php` abstracto en `core/src/Core/`
- `register()` — registra bindings en el container
- `boot()` — lógica post-registro (middlewares, events, etc.)
- Auto-descubrimiento de providers en `app/Providers/`
- Providers por defecto: `RouteServiceProvider`, `AuthServiceProvider`,
  `DatabaseServiceProvider`

```php
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('auth', function ($app) {
            return new Auth($app->make('session'), $app->make('db'));
        });
    }

    public function boot(): void
    {
        $this->app->on('user.login', fn($user) => log_info("Login: {$user->email}"));
    }
}
```

#### 4.3 Sistema de Assets Mejorado

- Mejorar integración con Vite o esbuild como alternativa a Tailwind standalone
- Hot Module Replacement en desarrollo
- Manifest de assets para producción (`mix-manifest.json` o `manifest.json`)
- Helper `mix('css/app.css')` que lee manifest y retorna URL versionada
- Compresión automática de assets en build

#### 4.4 Middleware Pipeline Mejorado

- Prioridad de middleware (before/after groups)
- Middleware aliases en config: `'auth' => AuthMiddleware::class`
- Middleware groups:
  `'web' => [CsrfMiddleware::class, SessionMiddleware::class]`
- `$router->middlewareGroup('web', [...])`

#### 4.5 Helpers Adicionales

- `session()` — acceso rápido a sesión
- `old()` — integración real con Session + Request
- `back()` — redirect a URL anterior
- `route('name')` — URL por nombre de ruta (requiere named routes)
- `mix()` — asset versionado
- `config()` — dot notation acceso a configuración
- `cache()` — acceso rápido a cache
- `event()` — disparar evento
- `auth()` — acceso a instancia de Auth
- `db()` — acceso a QueryBuilder
- `now()` — Carbon-like DateTime helper básico
- `today()` — fecha actual

#### Criterios de aceptación Fase 4

- [ ] `bootzen make:controller Post` genera controller con CRUD
- [ ] `bootzen migrate` ejecuta migraciones pendientes
- [ ] `bootzen route:list` muestra tabla de rutas con método, uri, name,
      middleware
- [ ] Service Provider registra y bootea correctamente
- [ ] Named routes funcionan con helper `route()`

---

### Fase 5 — Características Avanzadas (Semanas 14-18)

> **Meta**: Features que diferencian a BootZen de un script PHP básico. Colas,
> mail, uploads, i18n, JWT.

#### 5.1 Sistema de Colas / Jobs (Opcional)

- Driver sincrónico (default) y driver de base de datos
- `Job` abstracto con `handle()` y `failed()`
- `dispatch($job)->onQueue('emails')->delay(60)`
- Worker: `php .scripts/queue:work`
- Reintentos configurables
- Prioridad de colas

```php
class SendWelcomeEmail extends Job
{
    public function __construct(private User $user) {}

    public function handle(): void
    {
        // Enviar email
    }

    public function failed(Throwable $e): void
    {
        log_error("Falló email de bienvenida para {$this->user->email}");
    }
}

dispatch(new SendWelcomeEmail($user));
```

#### 5.2 Sistema de Mail

- Drivers: `smtp`, `sendmail`, `log` (desarrollo)
- Mailable abstracto con `build()`
- Plantillas de email como vistas
- Cola de emails opcional
- Adjuntos

```php
class WelcomeEmail extends Mailable
{
    public function __construct(private User $user) {}

    public function build(): void
    {
        $this->to($this->user->email)
             ->subject('Bienvenido')
             ->view('emails.welcome', ['name' => $this->user->name]);
    }
}

mail(new WelcomeEmail($user));
```

#### 5.3 Almacenamiento de Archivos (Storage)

- Driver `local` y configuración de paths
- `Storage::put()`, `get()`, `delete()`, `exists()`, `copy()`, `move()`
- `Storage::url()` para acceso público
- Upload de archivos desde Request: `$request->file('avatar')`
- Validación de archivos: `file`, `image`, `mimes:jpg,png`, `max:2048`
- Thumbnails básicos (si GD está disponible)
- Directorio `storage/uploads/` con symlink a `public/storage/`

#### 5.4 Internacionalización (i18n)

- Archivos de idioma en `lang/{locale}/{file}.php`
- Helper `__('messages.welcome')` con fallback
- Helper `__n('items', 5)` para plurales
- Detección de locale desde header Accept-Language, URL prefix, o sesión
- Middleware `LocaleMiddleware` para establecer locale
- Variables de idioma en vistas: `{{ __('messages.greeting') }}`

```
lang/
  es/
    messages.php    # return ['welcome' => 'Bienvenido', ...]
    validation.php  # Mensajes de error de validación
    auth.php        # Mensajes de autenticación
  en/
    messages.php
    validation.php
    auth.php
```

#### 5.5 JWT para APIs

- Crear `JwtAuth.php` como middleware opcional
- Generación de tokens con `firebase/php-jwt` (dependencia opcional)
- Middleware `JwtMiddleware` para proteger rutas API
- Refresh tokens
- Blacklist de tokens revocados
- Configuración: secret key, TTL, algoritmo

```php
$router->group(['prefix' => '/api/v1', 'middleware' => [JwtMiddleware::class]], function ($router) {
    $router->get('/profile', [ProfileApiController::class, 'show']);
    $router->put('/profile', [ProfileApiController::class, 'update']);
});

$router->post('/api/v1/login', [AuthApiController::class, 'login']); // Sin middleware
$router->post('/api/v1/refresh', [AuthApiController::class, 'refresh']);
```

#### 5.6 WebSocket Básico (Investigación)

- Evaluar Ratchet o ReactPHP como base
- Channel abstraction
- Broadcasting de eventos
- Autenticación de conexiones WebSocket
- Si la complejidad es alta → mover a módulo/plugin

#### Criterios de aceptación Fase 5

- [ ] Job se ejecuta en cola sincrónica y en BD
- [ ] Email se envía y llega correctamente
- [ ] Archivo se sube y es accesible via URL pública
- [ ] App funciona en español e inglés cambiando locale
- [ ] API protegida con JWT acepta/rechaza requests

---

### Fase 6 — Pulido y Lanzamiento Estable (Semanas 19-20)

> **Meta**: v2.0.0 estable, documentada, testeada y lista para uso en
> producción.

#### 6.1 Documentación Completa

- **Getting Started**: instalación, primer proyecto, estructura de carpetas
- **Routing**: definición, parámetros, grupos, middleware, named routes
- **Controllers**: convenciones, CRUD, inyección de dependencias
- **Models**: ORM, relaciones, query builder, migraciones
- **Views**: sistema de plantillas, layouts, componentes, helpers
- **Authentication**: configuración, guards, protección de rutas
- **Middleware**: crear custom, pipeline, aliases
- **Validation**: reglas, mensajes, validación custom
- **Events**: registro, disparo, listeners
- **CLI**: referencia de todos los comandos
- **Deployment**: configuración producción, optimizaciones, nginx/apache
- **API Reference**: documentación generada de todas las clases públicas

#### 6.2 Optimizaciones de Producción

- `config:cache` — serializa toda la config a un solo archivo
- `route:cache` — serializa rutas para evitar re-parsing
- Autoloader optimization (Composer `--optimize-autoloader --no-dev`)
- OPcache recomendaciones en docs
- Lazy loading de providers y servicios
- Minimizar boot time del Application

#### 6.3 Estabilidad y Compatibilidad

- PHP 8.2+ compatibility matrix test
- Tests de regresión completos
- SemVer estricto para API pública
- CHANGELOG completo desde v1.0.0
- Upgrade guide v1.x → v2.0
- Deprecation notices para APIs que cambiarán

#### 6.4 Comunidad y Ecosistema

- Template de issue en GitHub (bug report, feature request)
- Contributing guide
- Plugin/module skeleton template
- Ejemplos de proyectos:
  - Blog simple (CRUD + auth)
  - API REST (JWT + rate limiting)
  - Dashboard admin (auth + charts + export)

#### Criterios de aceptación Fase 6

- [ ] Documentación publicada y navegable
- [ ] Todos los tests pasan en PHP 8.2, 8.3, 8.4
- [ ] Proyecto ejemplo de blog funciona de inicio a fin
- [ ] `bootzen.phar` v2.0.0 publicado en GitHub Releases
- [ ] CHANGELOG actualizado con todos los cambios

---

## Resumen de Dependencias Nuevas

| Paquete             | Fase   | Uso                  | Requerido |
| ------------------- | ------ | -------------------- | --------- |
| predis/predis       | Actual | Cache Redis          | Opcional  |
| monolog/monolog     | Actual | Logging              | Opcional  |
| vlucas/phpdotenv    | Actual | Variables de entorno | Sí        |
| symfony/var-dumper  | Actual | Debug (dd)           | Dev only  |
| firebase/php-jwt    | 5      | JWT auth             | Opcional  |
| phpmailer/phpmailer | 5      | Envío de emails      | Opcional  |
| ratchet/ratchet     | 5      | WebSockets           | Opcional  |
| intervention/image  | 5      | Thumbnails           | Opcional  |

> Filosofía: todo es **opcional** excepto phpdotenv. El framework funciona sin
> Redis, sin Monolog, sin JWT. Si falta una dependencia, el feature se desactiva
> gracefully con un log de warning.

---

## Timeline Estimado

```
Fase 1  ████░░░░░░░░░░░░░░░░  Semanas 1-4   Cimientos MVC
Fase 2  ░░░░████░░░░░░░░░░░░  Semanas 5-7   Auth y Seguridad
Fase 3  ░░░░░░░░████░░░░░░░░  Semanas 8-10  Calidad y Observabilidad
Fase 4  ░░░░░░░░░░░░████░░░░  Semanas 11-13 DX y Productividad
Fase 5  ░░░░░░░░░░░░░░░░████  Semanas 14-18 Features Avanzadas
Fase 6  ░░░░░░░░░░░░░░░░░░██  Semanas 19-20 Pulido y Release
```

**Total: ~20 semanas** (ajustable según ritmo, priorizar Fases 1-3 como bloqueo
mínimo para un framework utilizable).

---

## Principios Rectores

1. **Simplicidad ante todo**: si una feature puede ser un plugin, que sea un
   plugin.
2. **Convención sobre configuración**: defaults sensatos, configuración solo
   donde sea necesario.
3. **Seguridad por defecto**: CSRF, hashing, sanitización, headers — siempre
   activos.
4. **Zero-dependencia core**: el framework funciona sin Redis, sin queues, sin
   mail. Las dependencias opcionales se activan si existen.
5. **Documentación como código**: cada feature documentada con ejemplo antes de
   marcarla como completa.
6. **Tests first**: escribir test antes o junto a la feature, no después.
7. **Backward compatible**: no romper APIs públicas sin deprecation notice
   previo.

---

## Notas sobre Priorización

La regla del 80% aplicada a BootZen:

- **80% de proyectos web PHP necesitan**: router, controllers, views, BD, auth,
  sesiones, formularios con CSRF, validación, logging → **todo esto va en Fases
  1-3**.
- **20% de proyectos necesitan**: colas, mail avanzado, i18n, JWT, WebSockets →
  **esto va en Fase 5 como opcional**.

Si el tiempo es limitado, **Fases 1-3 son el MVP** que convierte a BootZen en un
framework real y utilizable. Las Fases 4-6 son diferenciadores.
