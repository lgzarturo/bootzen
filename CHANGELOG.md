# CHANGELOG

## [2.0.0] - 2026-10-27

Primera versión estable. BootZen pasa de microframework en `.phar` a
boilerplate de monolitos para MVP: PHP 8.2+, SQLite, Tailwind v4 sin Node,
JavaScript nativo y vistas con componentes `<x-*>`, con el framework como
código fuente dentro de cada proyecto y sin dependencias de Composer en
producción. Pensado para construirse con IA generativa y desplegarse en
hosting compartido, VPS o Docker. Detalle por fase abajo; el plan está en
[plan/04_PLAN-MVP-MONOLITO.md](plan/04_PLAN-MVP-MONOLITO.md).

**Cambios incompatibles con la v1.x.** Cómo pasar un proyecto:
[docs/MIGRACION-V1-V2.md](docs/MIGRACION-V1-V2.md).

### Fase 7: despliegue

#### Agregado

- `php bin/bootzen deploy:build [--version=] [--no-css]`: arma
  `build/<app>-<versión>/` y su `.zip` (o `.tar.gz`) sin `.env`, base,
  tests ni `vendor/`, con el CSS compilado y las vistas precompiladas.
- Instalador web de un solo uso para hostings sin terminal:
  `INSTALL_ENABLED=true` habilita `/_bootzen/instalar`, que pide la
  `APP_KEY`, ejecuta las migraciones y se bloquea solo
  (`storage/install.lock`). Límite de 5 intentos cada 10 minutos.
- `php bin/bootzen framework:update [--from=] [--force]`: copia
  `framework/` desde la instalación local (`BOOTZEN_HOME` o `~/.bootzen`),
  no baja de versión ni pisa cambios locales sin `--force` (los detecta con
  `framework/MANIFEST.json`) y deja un respaldo en `storage/backups/`.
- En cada proyecto: `.htaccess` en la raíz para hostings donde no puedes
  apuntar a `public/`, `Dockerfile` y `docker-compose.yml` (Apache, OPcache,
  volúmenes para datos y archivos), y en `deploy/` las configuraciones de
  Nginx, Caddy y un pool de PHP-FPM, más `deploy.sh` para VPS con
  `releases/`, `shared/` y vuelta atrás.
- Guías en `docs/deploy/` del proyecto (hosting compartido, VPS y Docker),
  probadas con la app demo en Apache, Nginx, Caddy y Docker con SQLite y
  MySQL. El CI repite hosting compartido y Docker en cada cambio.
- Guía de migración desde la v1 en `docs/MIGRACION-V1-V2.md`.

#### Cambiado

- Las vistas compiladas se nombran según la ruta relativa al proyecto, así
  las precompiladas en `deploy:build` sirven en el servidor.
- `doctor`: `APP_KEY` vacía en producción es un error, revisa la carpeta
  real de la base SQLite, acepta variables del servidor sin `.env` y avisa
  si el instalador web sigue activo.
- `release.yml` usa la sección escrita a mano del CHANGELOG como notas del
  release cuando existe, y el paquete incluye el CHANGELOG y la guía de
  migración.

#### Corregido

- Las migraciones de `--auth` y `--demo` funcionan también en MySQL.

### Fase 6: capa para IA

#### Agregado

- Recetas en `docs/ai/recipes/` del proyecto: crear un CRUD, proteger rutas,
  endpoint JSON, crear un componente (modal con `<dialog>`), migraciones,
  subir archivos y enviar correos, cada una con tests y errores comunes.
- `docs/ai/reference.md` con la API pública generada desde los PHPDoc
  (`bin/bootzen-reference`; un test avisa si queda vieja) y `llms.txt` en
  el proyecto y en el repositorio.
- Skills `crear-recurso`, `crear-componente` y `revisar-seguridad` en
  `.claude/skills/` y `.agents/skills/`.
- `bootzen-new mi-app --demo`: auth más una app de tareas por usuario con
  modal de borrado, JSON paginado, filtros, seeder y tests.
- Subida de archivos: `$request->file('campo')` con `store()` (privado) y
  `storePublic()` (en `public/uploads`), nombre aleatorio, extensión según el
  tipo detectado y bloqueo de extensiones ejecutables. `TestClient::file()` y
  `TestClient::image()` para los tests.
- `make:controller --json` (sin vista).

#### Cambiado

- `asset()` devuelve rutas relativas al dominio (`/assets/app.css?v=...`), con
  el subdirectorio de `APP_URL` si lo hay.
- El login y el registro del scaffold redirigen a `config('auth.home')`.
- `make:component` usa la prop `title`, como los componentes incluidos.
- El CSS del skeleton se genera desde un proyecto `--demo`.

### Fase 5: CLI y generadores

#### Agregado

- `make:resource Modelo campo:tipo[:nullable] ... [--table=]`: migración,
  modelo, controlador con validación, ruta `resource`, vistas index, create,
  edit y show con componentes, y tests de feature que pasan `composer check`
  sin tocar nada. Tipos: `string`, `text`, `email`, `int`, `float`, `bool`,
  `date` y `datetime`. Plurales en inglés; `--table` nombra tabla, rutas y
  vistas. Genera SQL de SQLite o MySQL según la conexión.
- `make:controller`, `make:model` (`--migration`, `--table`),
  `make:migration` (con `CREATE TABLE` si se llama `crear_x`),
  `make:middleware`, `make:seeder`, `make:test` (`--unit`) y
  `make:component` (`--class`), desde plantillas en `framework/stubs`.
  Ningún generador sobrescribe archivos.
- `route:list`, `config:cache`, `config:clear`, `key:generate`, `doctor`
  (PHP, extensiones, permisos, `.env`, `APP_DEBUG` en producción, base de
  datos, migraciones pendientes y CSS) y `check` (formato, PHPStan y tests).
- `BootZen\Testing\TestClient` y `TestResponse` con `client()` en los
  proyectos: peticiones con cookies de sesión, `actingAs`, `from`,
  `followingRedirects` y aserciones de estado, HTML, JSON, cabeceras, sesión,
  errores de validación y base de datos.
- Componentes `<x-textarea>` y `<x-checkbox>`.

#### Cambiado

- `back()` y la redirección por error de validación vuelven a la página del
  `Referer` si es del mismo sitio.
- En `APP_ENV=testing` se ignora la caché de configuración.
- Los tests del skeleton y del scaffold `--auth` usan `client()`.

### Fase 4: sesión, seguridad y autenticación

#### Agregado

- Sesión propia en archivos (`storage/sessions`) con `StartSession`: flash,
  `old()`, errores de validación, token CSRF y cookie `HttpOnly`,
  `SameSite=Lax` y `Secure` en HTTPS. Si la petición no usa la sesión no se
  crea archivo ni cookie. Driver `array` para tests.
- `VerifyCsrfToken`: token en `_token` o en la cabecera `X-CSRF-TOKEN`,
  respuesta 419 con su vista. Rutas excluidas con `session.csrf_except`.
- `Validator` con reglas `required`, `nullable`, `string`, `integer`,
  `numeric`, `boolean`, `array`, `email`, `url`, `min`, `max`, `between`,
  `in`, `confirmed`, `date`, `regex`, `unique`, `exists`, `file`, `image` y
  `mimes`, comodines (`items.*.precio`), closures y mensajes en español.
  `Request::validate()` vuelve al formulario con errores e input, o responde
  422 en JSON.
- `Response::with()`, `withErrors()` y `withInput()`; helpers `session()`,
  `back()`, `auth()` y `mailer()`.
- `Auth` con guard de sesión (`attempt`, `login`, `logout`, `user`,
  `intended`), `Hash` (Argon2id o bcrypt) y `PasswordReset`.
- Middleware `auth`, `guest`, `throttle:max,minutos` (`RateLimiter` sobre la
  caché) y `SecurityHeaders` (nosniff, frame options, referrer policy, HSTS en
  HTTPS y CSP configurable). Middleware con parámetros
  (`ParameterizedMiddleware`).
- `Mailer` con drivers `log` y `array`.
- `bootzen-new --auth`: registro, login, logout y recuperación de contraseña
  con vistas, migración, límite de intentos y tests.
- Skeleton: grupos `web` y `api`, `routes/api.php`, `config/session.php`,
  `auth.php`, `mail.php`, `cache.php` y `security.php`, vista 419, mensajes
  flash en el layout y `<x-input>` con `old()`.

#### Cambiado

- El campo CSRF se llama `_token` y los helpers de sesión ya no usan
  `session_start()`.
- `Request::getClientIp()` ignora las cabeceras de proxy cuando el cliente se
  conecta directo con una IP pública, para que no se pueda falsear la IP.
- `Cache` acepta un directorio `null` para guardar en memoria.

### Fase 3: datos

#### Agregado

- `BootZen\Database\Connection` sobre PDO: SQLite por defecto (WAL, claves
  foráneas, `busy_timeout`) y MySQL. Consultas con parámetros enlazados y
  `QueryException` con el SQL y los valores. Transacciones anidables.
- `QueryBuilder`: `select`, `where` (y `orWhere`, `whereIn`, `whereNull`,
  `whereRaw`), `join`, `orderBy`, `limit`, `paginate`, `insert`, `update` y
  `delete`. Identificadores validados y entre comillas; `update()` y
  `delete()` exigen un `where`.
- `Paginator` serializable a JSON y componente `<x-pagination>`.
- `Model` (Active Record ligero): `find`, `findOrFail` (404 automático),
  `create`, `save`, `update`, `delete`, `$fillable`, `$hidden`, `$casts`
  (`int`, `float`, `bool`, `string`, `array`, `datetime`, `date`), timestamps,
  `hasMany` y `belongsTo`.
- Migraciones con SQL directo (`Migrator`, por lotes y con transacción en
  SQLite) y seeders (`Seeder`, `SeederRunner`).
- Comandos `migrate`, `migrate:rollback`, `migrate:status`, `db:seed`,
  `db:fresh` (pide `--force` en producción) y `db:backup` (`VACUUM INTO`).
- Helper `db()`. Devolver un modelo o paginador desde un controlador responde
  JSON.
- Skeleton: `config/database.php`, variables `DB_*`, `database/migrations`,
  `database/seeds/DatabaseSeeder.php`, `app/Models` y tests con SQLite en
  memoria y migraciones aplicadas en `createApp()`.
- CI: tests de integración contra MySQL 8.4.

### Fase 2: vistas, componentes y frontend

#### Agregado

- Motor de vistas (`BootZen\View`): vistas `.php` con una lista cerrada de
  directivas (`{{ }}`, `{!! !!}`, `{{-- --}}`, `@{{ }}`, `@if`, `@foreach`
  con `@empty`, `@csrf`, `@method`, `@error`, `@props`) y componentes
  `<x-*>` con slots con nombre, `$attributes` (`merge`, `class`) y clases
  opcionales en `app/View/Components`. Compila a `storage/cache/views`,
  conserva la numeración de líneas y los errores apuntan a la vista original.
- Helpers `view()` y `class_list()`; `ExceptionHandler` renderiza
  `errors/{codigo}` con el motor y la página de depuración muestra la línea de
  la vista original.
- Skeleton: componentes `<x-layouts.app>`, `<x-navigation>`, `<x-card>`,
  `<x-button>`, `<x-alert>`, `<x-form>` y `<x-input>`; bienvenida y páginas de
  error con el layout.
- Tailwind CSS v4 con el CLI standalone: `bin/bootzen` lo descarga en
  `storage/bin/` y verifica su SHA-256 contra el release (v4.3.3). CSS
  precompilado en `skeleton/public/assets/app.css`.
- `bin/bootzen` en el skeleton: `serve` (con Tailwind en modo watch),
  `css:install`, `css:build`, `css:watch`, `view:cache`, `view:clear`.
- Runtime `public/assets/js/bootzen.js`: componentes `data-component` con
  limpieza al quitarlos del DOM, `request()` con JSON y CSRF, `toast()`,
  `data-confirm`, `data-remote`, `data-dismiss` y botones deshabilitados al
  enviar. Componentes de ejemplo `counter` y `menu`.
- Tests del runtime JS con `node --test` y jsdom; CI compila el CSS con el
  binario real y falla si el CSS del skeleton quedó desactualizado.

#### Eliminado

- `BootZen\Core\Component` y los componentes como clases PHP
  (`framework/src/Components`): ahora son vistas `<x-*>` del skeleton.

### Fase 1: núcleo HTTP

#### Agregado

- `Env`: carga `.env` sin dependencias (comillas, comentarios, `export`,
  interpolación `${VAR}`), sin sobrescribir variables reales del entorno.
- `Config`: archivos de `config/` con notación de puntos (`config('app.name')`)
  y caché en un solo archivo (`Config::writeCache()`).
- `Logger` de archivo diario con niveles PSR-3 y `Cache` de archivos con
  `remember()`, `increment()` y expiración.
- Router: patrones compilados una vez, restricciones `{id:\d+}` y `where()`,
  rutas con nombre y `route()`, grupos con `as`, `resource()`, respuestas
  405 con `Allow`, `OPTIONS` y `HEAD` automáticos.
- `Container::call()`: inyecta parámetros de ruta por nombre (con conversión
  a `int`, `float` y `bool`) y dependencias por tipo.
- `Pipeline` único para middleware global y de ruta, con alias y grupos.
- `Request`: `input()`, `all()`, `only()`, `except()`, `has()`, `filled()`,
  `boolean()`, `integer()`, `param()`, `wantsJson()` y `_method` para
  formularios.
- `Response::noContent()` y `withHeaders()`.
- `ExceptionHandler`: vistas `errors/{codigo}.php`, JSON automático, página
  de depuración con `APP_DEBUG` y registro de errores 5xx en el log.
- Helpers `app()`, `config()`, `route()` y `cache()`; `env()` convierte
  `true`, `false`, `null` y `empty`.
- Skeleton: `.env.example`, `config/app.php`, páginas de error, rutas con
  nombre, alias `cors` y grupo `api`. `bootzen-new` genera `.env` con una
  `APP_KEY` aleatoria.
- CI: job de cobertura con `pcov`.

#### Cambiado

- `Application` recibe la raíz del proyecto (`new Application(dirname(__DIR__))`)
  y carga `.env` y `config/` al construirse. `boot()` se elimina.
- El router lanza `HttpException` 404/405 en vez de devolver respuestas.
- `Response::json()` no escapa barras ni caracteres Unicode y acepta listas.

#### Corregido

- `Request::fromGlobals()` no incluía `Content-Type` ni `Content-Length` en
  las cabeceras (en `$_SERVER` llegan sin el prefijo `HTTP_`), así que
  `isJson()` siempre era falso en peticiones reales. Un cuerpo JSON que no es
  objeto ya no rompe el tipo del cuerpo.
- Un prefijo de grupo generaba paths con doble barra y `//ruta` se
  interpretaba como host.
- El router respondía todo `OPTIONS` con cabeceras CORS abiertas aunque no se
  registrara el middleware.

#### Eliminado

- Handlers `'Clase@metodo'`: usa `[Clase::class, 'metodo']`.
- `Application::send()`: `handle()` envía la respuesta y `run()` la devuelve.
- `Application::__call()` (proxy mágico al router) y `Router::middleware()`
  para la "siguiente ruta": usa `$app->routes()` y `->middleware()` en la ruta.
- Respuesta automática de `CorsMiddleware` a todo `OPTIONS`: ahora CORS se
  aplica solo donde se registra el middleware.

### Fase 0: limpieza y reorganización

#### Agregado

- `framework/bootstrap.php` con autoloader propio para `BootZen\` y `App\`:
  los proyectos funcionan sin `vendor/` en producción.
- `skeleton/`: el boilerplate como archivos reales (rutas, controlador,
  vista, tests, `AGENTS.md` para agentes de IA, configuración de Pest, PHPStan
  y PHP CS Fixer).
- `bin/bootzen-new`: instalador de proyectos en PHP, multiplataforma.
- `HttpException`: `abort()` la lanza y `Application` responde con su código
  de estado en HTML o JSON.
- Helper `base_path()`, ruta `/up` de health check en el skeleton.
- `composer.json` de desarrollo en la raíz, tests del framework con Pest y
  workflow `ci.yml` (PHP 8.2, 8.3 y 8.4 más verificación del proyecto
  generado).

#### Cambiado

- `core/src` se mueve a `framework/src`.
- `init_project.sh` pasa a ser un envoltorio de `bin/bootzen-new`.
- `redirect()`, `json_response()` y `download()` retornan un `Response` en vez
  de terminar la ejecución. `abort()` lanza `HttpException`.
- El release publica un tarball del framework y el skeleton en lugar de
  `bootzen.phar`, después de pasar `composer check`.
- `install.sh` solo exige PHP y git; Composer es opcional.

#### Eliminado

- Distribución en `.phar` (`build-phar.php`, `core/stub.php`).
- `Cache` y `CacheTenant` (Redis/Predis) y la caché de `Component`.
- `HomeController` y `ApiRoutes` de ejemplo dentro del framework.
- Polyfills de `rand`, `mt_rand`, `random_int` y `random_bytes`.
- Scripts `bin/make.sh`, `start.sh`, `analyzer.sh` y `count_lines.sh`
  (los reemplaza la CLI `bin/bootzen` en la fase 5).
- Dependencias de runtime del proyecto generado: `vlucas/phpdotenv`,
  `predis/predis`, `monolog/monolog` y `symfony/var-dumper`.

---

## [1.1.0] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.1.0.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.
- Se optimiza el proceso de generación del archivo `.phar`.
- Se actualiza el script de instalación para manejar mejor las configuraciones
  de shell.

---

## [1.0.9] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.9.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.
- Se elimina la firma del archivo `.phar` para evitar problemas de integridad al
  copiar/renombrar.

---

## [1.0.8] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.8.

---

## [1.0.7] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.7.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.5] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.5.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.4] - 2025-10-18

### Cambios

- BootZen ahora distribuye el framework como archivo `.phar` versionado
  (`bootzen-<version>.phar`).
- El workflow de GitHub Actions compila y publica automáticamente el `.phar` en
  cada release.
- El instalador descarga el último `.phar` desde GitHub Releases y lo guarda en
  `~/.bootzen/bootzen.phar`.
- Al crear un nuevo proyecto con `init_project.sh`, el `.phar` se copia
  automáticamente a `public/` del proyecto.
- El archivo `public/index.php` está preparado para cargar y usar el framework
  desde el `.phar` si existe.
- Se actualiza la documentación para reflejar el nuevo flujo de instalación y
  uso del `.phar`.

### Notas

> Esta versión marca el inicio del sistema de distribución y actualización
> automática del framework mediante archivos `.phar` versionados. Facilita la
> actualización y el uso de BootZen en nuevos proyectos.

---

## [1.0.2] - 2025-09-24

### Cambios

- Se agrega advertencia visible en README.md sobre el estado no estable del
  proyecto.
- Se crea y documenta el archivo CHANGELOG.md.
- Se mejora la documentación para clarificar el uso recomendado y el estado de
  los releases.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.1] - 2025-09-24

### Cambios

- Mejoras menores en scripts de instalación y actualización.
- Se agrega el script `updater.sh` para actualizar BootZen automáticamente.
- Documentación ampliada en README.md.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.0] - 2025-09-23

### Cambios

- Inicialización del proyecto BootZen.
- Estructura base del microframework PHP.
- Script de instalación automática (`install.sh`).
- Script principal para crear proyectos (`init_project.sh`).
- Documentación inicial.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## Estado actual

**BootZen aún no cuenta con un release estable.**

- Los tags publicados son versiones preliminares para pruebas y desarrollo.
- Se recomienda esperar al primer release oficial antes de usar en producción.
- Para reportar errores o sugerencias, visita
  [GitHub Issues](https://github.com/lgzarturo/bootzen/issues).
