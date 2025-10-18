# CHANGELOG

## [1.0.7] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.7.

## [1.0.7] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.7.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en producción hasta que se publique un release oficial.

## [1.0.5] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.5.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en producción hasta que se publique un release oficial.

## [1.0.4] - 2025-10-18

### Cambios

- BootZen ahora distribuye el framework como archivo `.phar` versionado (`bootzen-<version>.phar`).
- El workflow de GitHub Actions compila y publica automáticamente el `.phar` en cada release.
- El instalador descarga el último `.phar` desde GitHub Releases y lo guarda en `~/.bootzen/bootzen.phar`.
- Al crear un nuevo proyecto con `init_project.sh`, el `.phar` se copia automáticamente a `public/` del proyecto.
- El archivo `public/index.php` está preparado para cargar y usar el framework desde el `.phar` si existe.
- Se actualiza la documentación para reflejar el nuevo flujo de instalación y uso del `.phar`.

### Notas

> Esta versión marca el inicio del sistema de distribución y actualización automática del framework mediante archivos `.phar` versionados. Facilita la actualización y el uso de BootZen en nuevos proyectos.

## [1.0.2] - 2025-09-24

### Cambios

- Se agrega advertencia visible en README.md sobre el estado no estable del proyecto.
- Se crea y documenta el archivo CHANGELOG.md.
- Se mejora la documentación para clarificar el uso recomendado y el estado de los releases.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en producción hasta que se publique un release oficial.

## [1.0.1] - 2025-09-24

### Cambios

- Mejoras menores en scripts de instalación y actualización.
- Se agrega el script `updater.sh` para actualizar BootZen automáticamente.
- Documentación ampliada en README.md.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en producción hasta que se publique un release oficial.

---

## [1.0.0] - 2025-09-23

### Cambios

- Inicialización del proyecto BootZen.
- Estructura base del microframework PHP.
- Script de instalación automática (`install.sh`).
- Script principal para crear proyectos (`init_project.sh`).
- Documentación inicial.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en producción hasta que se publique un release oficial.

---

## Estado actual

**BootZen aún no cuenta con un release estable.**

- Los tags publicados son versiones preliminares para pruebas y desarrollo.
- Se recomienda esperar al primer release oficial antes de usar en producción.
- Para reportar errores o sugerencias, visita [GitHub Issues](https://github.com/lgzarturo/bootzen/issues).
