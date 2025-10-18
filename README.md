# BootZen: a modular micro framework

La versión actual v1.0.7 de BootZen es una versión preliminar para pruebas y desarrollo.

> Los tags publicados son versiones preliminares para pruebas y desarrollo. No se recomienda su uso en producción hasta que se publique el primer release oficial. Consulta el archivo [CHANGELOG.md](CHANGELOG.md) para más detalles.

**Arranca en zen: Desarrolla con calma, crece con poder.**

BootZen es un microframework PHP nativo, diseñado para crear aplicaciones mobile-first que brillan en SEO y pueden evolucionar fácilmente hacia soluciones SaaS modulares. Su objetivo es ser simple, memorable y modular, invitando a los desarrolladores a fluir sin estrés y construir proyectos robustos desde el primer momento.

---

## ¿Qué es BootZen?

BootZen automatiza la creación de la base de tu proyecto PHP con una estructura moderna, herramientas de desarrollo, dependencias y scripts auxiliares. El script `init_project.sh` te permite iniciar un nuevo proyecto en segundos, listo para crecer y adaptarse a tus necesidades.

### Tagline

> Arranca en zen: Desarrolla con calma, crece con poder.

---

## Instalación automática y uso del .phar versionado

BootZen ahora distribuye el framework como un archivo `.phar` versionado (por ejemplo, `bootzen-1.0.4.phar`) que se descarga automáticamente desde GitHub Releases y se integra en cada nuevo proyecto.

El proceso automatizado incluye:

- Clona el repositorio en `~/.bootzen`.
- Detecta si usas bash o zsh y agrega la ruta al PATH en el archivo de configuración correspondiente.
- Descarga el último archivo `.phar` publicado en GitHub Releases y lo guarda en `~/.bootzen/bootzen.phar`.
- Al crear un nuevo proyecto con `init_project.sh`, el archivo `.phar` se copia automáticamente a la carpeta `public/` del proyecto generado.
- El archivo `public/index.php` está preparado para cargar y usar el framework desde el `.phar` si existe.
- Recarga la configuración del shell y verifica que el comando `init_project.sh` esté disponible globalmente.

Instala BootZen ejecutando:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/install.sh)"
```

### ¿Cómo funciona el .phar en los proyectos?

Cada vez que creas un nuevo proyecto con `init_project.sh`, el archivo `bootzen.phar` se copia a `public/` y el `index.php` lo carga automáticamente si está presente. Esto permite que el framework se ejecute directamente desde el archivo `.phar`, facilitando actualizaciones y distribución.

### Actualización automática

Puedes actualizar BootZen y el archivo `.phar` a la última versión estable (release, tag o main) ejecutando:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/updater.sh)"
```

El script detecta si hay un release disponible y actualiza a ese release. Si no hay release, actualiza al último tag. Si no hay tag, actualiza la rama main. Si BootZen no está instalado, te pedirá instalarlo primero. El archivo `.phar` se actualizará automáticamente en tu instalación global y se usará en los nuevos proyectos.

---

## Estructura del Proyecto

El script genera una estructura de carpetas pensada para proyectos monolíticos y modulares:

- `public/` (archivos públicos, index.php, assets)
- `src/` (Controllers, Models, Views, Services, Database, Core, Middleware, Helpers)
- `config/` (configuración)
- `tests/` (Unit, Feature)
- `views/` (layouts, components, pages)
- `storage/` (logs, cache, uploads)
- `.vscode/` (configuración recomendada para VSCode)
- `.scripts/` (scripts auxiliares)
- `resources/` (css, js)

---

## Dependencias

### Composer (PHP)

Las dependencias principales y de desarrollo se definen en `composer.json`:

**Dependencias principales:**

- `php` (>=8.2)
- `vlucas/phpdotenv`: Manejo de variables de entorno.
- `predis/predis`: Cliente Redis para PHP.
- `monolog/monolog`: Logging avanzado.
- `symfony/var-dumper`: Herramienta de depuración.

**Dependencias de desarrollo:**

- `pestphp/pest`: Framework de testing moderno.
- `friendsofphp/php-cs-fixer`: Formateador de código PHP.
- `phpstan/phpstan`: Análisis estático de código.
- `fakerphp/faker`: Generador de datos falsos para pruebas.
- `mockery/mockery`: Mocking para tests.

### NPM (Frontend)

Las dependencias de frontend se definen en `package.json`:

**DevDependencies:**

- `tailwindcss`: Framework CSS moderno y utilitario.
- `husky`: Hooks de git para flujos de trabajo.
- `lint-staged`: Linting automático en pre-commit.
- `concurrently`: Ejecuta scripts en paralelo.
- `@tailwindcss/forms`: Plugin de Tailwind para formularios.
- `@tailwindcss/typography`: Plugin de Tailwind para tipografía.

**Scripts útiles:**

- `dev`: Compila Tailwind y ejecuta el servidor PHP en modo desarrollo.
- `build`: Compila CSS para producción.
- `serve`: Inicia el servidor PHP.
- `prepare`: Instala Husky.

**Lint-Staged:**
Automatiza la revisión de código PHP en cada commit:

- Formatea con PHP CS Fixer
- Analiza con PHPStan
- Ejecuta tests con Pest

---

## Uso del Script

```bash
./init_project.sh [NOMBRE_DEL_PROYECTO]
```

Si no se especifica nombre, se usará `my-php-app` por defecto.

El script:

1. Crea la estructura de carpetas y archivos base.
2. Copia scripts auxiliares y asigna permisos.
3. Genera archivos de configuración para Composer, NPM, VSCode, PHP CS Fixer, PHPStan, Pest, PHPUnit, Tailwind, etc.
4. Inicializa git y realiza el primer commit.
5. Instala dependencias de Composer y NPM.
6. Configura Husky y lint-staged para pre-commit.
7. Muestra instrucciones para continuar el desarrollo.

---

## Filosofía BootZen

- **Simple:** Arranca rápido, sin configuraciones complejas.
- **Memorable:** Estructura clara y fácil de recordar.
- **Modular:** Listo para crecer como SaaS o monolito.
- **Fluidez:** Herramientas que ayudan, no estorban.
- **Mobile-first & SEO:** Pensado para apps modernas y visibles.

---

## Requisitos

- Bash
- Composer
- NPM
- Git
- OpenSSL

---

## ¿Listo para fluir?

1. Ejecuta el script y entra al directorio del proyecto.
2. Corre `npm run dev` para compilar Tailwind y levantar el servidor.
3. Configura tu base de datos en `.env`.
4. ¡Comienza a desarrollar con calma y poder!

---

## Autor

Desarrollado por [Arturo Lopez](https://github.com/lgzarturo) desde Cancún 🇲🇽🌴

---

## Licencia

Este proyecto está bajo la licencia [MIT](./LICENSE).
Copyright © 2025 [Arturo Lopez](https://github.com/lgzarturo)
