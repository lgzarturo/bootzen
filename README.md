# BootZen: boilerplate PHP para MVPs monolíticos

> **Estado:** en desarrollo hacia la v2.0. La v1.x (distribuida como `.phar`)
> queda descontinuada. Consulta el [CHANGELOG](CHANGELOG.md) y el
> [plan](plan/04_PLAN-MVP-MONOLITO.md).

**Arranca en zen: Desarrolla con calma, crece con poder.**

BootZen es una base de código para construir aplicaciones MVP con arquitectura
de monolito, pensada para trabajar junto con IA generativa y desplegar sin
fricción en hosting compartido, VPS o Docker.

## Stack

- **PHP 8.2+** sin dependencias de Composer en producción
- **SQLite** como base de datos por defecto (MySQL opcional), con migraciones en SQL directo
- **Tailwind CSS v4** con CLI standalone, sin Node
- **JavaScript nativo** con módulos ES, sin bundler
- **Plantillas `.php` con componentes `<x-*>`** compiladas y cacheadas
- **Sesión, CSRF, validación y autenticación** incluidas; registro, login y
  recuperación de contraseña con `bootzen-new mi-app --auth`
- **Capa para IA**: `AGENTS.md`, recetas, referencia de la API generada desde
  el código, skills y `make:resource`; `bootzen-new mi-app --demo` trae una
  app de tareas completa para tomar de ejemplo

## Instalación rápida

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/install.sh)"
bootzen-new mi-app
cd mi-app
php -S localhost:8000 -t public
```

Más opciones (manual, Windows, actualización) en [INSTALL.md](INSTALL.md).

## Qué obtienes al crear un proyecto

```
mi-app/
├── app/Controllers/      # Tu código (namespace App\)
├── bin/bootzen           # CLI: serve, make:resource, migrate, doctor...
├── bootstrap/app.php     # Construye la aplicación
├── routes/               # web.php (páginas) y api.php (JSON)
├── resources/views/      # Vistas y componentes <x-*>
├── resources/css/        # Fuente de Tailwind
├── public/               # Document root: index.php, CSS compilado y JS
├── storage/              # Logs y cachés
├── database/             # SQLite local
├── tests/                # Pest
├── framework/            # BootZen como código fuente
├── AGENTS.md             # Guía para agentes de IA (CLAUDE.md la importa)
└── composer.json         # Solo herramientas de desarrollo
```

El framework vive dentro del proyecto como código legible. Eso permite que la
IA lo entienda y que el proyecto se despliegue subiendo archivos, sin
`composer install` en el servidor.

## Desarrollo del framework

```bash
git clone https://github.com/lgzarturo/bootzen
cd bootzen
composer install
composer check                      # formato, PHPStan nivel 8 y tests
npm ci && npm run test:js           # tests del runtime JS (solo desarrollo del repo)
php bin/bootzen-new demo --no-git   # genera un proyecto de prueba
```

Estructura del repositorio:

- `framework/`: código del framework (namespace `BootZen\`)
- `skeleton/`: el boilerplate tal cual se copia a un proyecto nuevo
- `bin/bootzen-new`: instalador de proyectos
- `tests/`: tests del framework y del instalador
- `plan/`: hoja de ruta y seguimiento

## Filosofía

- **Simple:** una sola forma de hacer cada cosa.
- **Portable:** sin dependencias en producción, cualquier hosting con PHP sirve.
- **Pensado para IA:** código explícito, tipado estricto y una guía `AGENTS.md`
  en cada proyecto.
- **Verificable:** `composer check` es la definición de "terminado".

## Autor

Desarrollado por [Arturo Lopez](https://github.com/lgzarturo) desde Cancún 🇲🇽🌴

## Licencia

Este proyecto está bajo la licencia [MIT](./LICENSE). Copyright © 2025
[Arturo Lopez](https://github.com/lgzarturo)
