# CHANGELOG

## [Sin publicar] - 2.0.0-dev

Inicio de la v2.0 según [plan/04_PLAN-MVP-MONOLITO.md](plan/04_PLAN-MVP-MONOLITO.md).
Fase 0: limpieza y reorganización. **Cambios incompatibles con la v1.x.**

### Agregado

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

### Cambiado

- `core/src` se mueve a `framework/src`.
- `init_project.sh` pasa a ser un envoltorio de `bin/bootzen-new`.
- `redirect()`, `json_response()` y `download()` retornan un `Response` en vez
  de terminar la ejecución. `abort()` lanza `HttpException`.
- El release publica un tarball del framework y el skeleton en lugar de
  `bootzen.phar`, después de pasar `composer check`.
- `install.sh` solo exige PHP y git; Composer es opcional.

### Eliminado

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
