# CHANGELOG

## [Sin publicar] - 2.0.0-dev

Inicio de la v2.0 según [plan/04_PLAN-MVP-MONOLITO.md](plan/04_PLAN-MVP-MONOLITO.md).
**Cambios incompatibles con la v1.x.**

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
