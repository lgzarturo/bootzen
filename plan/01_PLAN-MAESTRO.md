# Plan Maestro: BootZen Framework — PHAR Core + Arquitectura de Plugins

## Contexto

BootZen (v1.1.0) es un microframework PHP distribuido como `.phar` con routing,
DI container, componentes UI, cache Redis, y scaffolding via shell scripts. Sin
embargo, el plan de desarrollo anterior (`plan/02_PLAN-DESARROLLO.md`) asume un
enfoque monolítico donde todo vive en el core.

**La nueva visión** es radicalmente diferente: mantener un **PHAR mínimo y
autocontenido** con solo lo esencial para boot + route + respond, y que todo lo
demás sea **plugins como paquetes Composer**, auto-descubiertos desde un
directorio `plugins/`.

### Problema actual

- Faltan piezas fundamentales: base Controller, Config, Session, Database,
  Validation, Views
- No existe sistema de plugins ni Service Providers
- El stub referencia un `index.php` inexistente
  (`require 'phar://bootzen.phar/index.php'`)
- Cache Redis y Components concretos están en core pero tienen dependencias
  externas
- No hay `composer.json` en el repo del framework (solo en proyectos generados)

### Resultado esperado

Un framework MVC donde `bootzen.phar` (~15 clases) funciona en cualquier OS, y
las features adicionales se instalan con `composer require bootzen/*` y se
auto-inyectan al leer el directorio `plugins/`.

---

## Arquitectura Objetivo

```
bootzen.phar (CORE — sin dependencias externas)
├── Core/Application.php          # Front controller + boot lifecycle
├── Core/Container.php            # IoC/DI con auto-wiring
├── Core/Router.php               # Routing con params dinámicos y grupos
├── Core/Route.php                # Value object de ruta
├── Core/Request.php              # Abstracción HTTP request
├── Core/Response.php             # Abstracción HTTP response
├── Core/Controller.php           # Base controller (NUEVO)
├── Core/Config.php               # Carga config/ con dot-notation (NUEVO)
├── Core/ServiceProviderInterface.php  # Contrato register/boot (NUEVO)
├── Core/ServiceProvider.php      # Clase abstracta convenience (NUEVO)
├── Core/PluginLoader.php         # Auto-descubrimiento de plugins (NUEVO)
├── Core/EventDispatcher.php      # Eventos mínimos (NUEVO)
├── Core/MiddlewareInterface.php  # Contrato middleware
├── Core/Component.php            # Base abstracta de componentes UI
└── Helpers/helpers.php           # env, dd, config, app, url, e, etc.

plugins/ (Composer packages — auto-descubiertos)
├── bootzen/database              # Connection, QueryBuilder, Model, Migration
├── bootzen/session               # Session, flash, CSRF integration
├── bootzen/validation            # Validator, request->validate()
├── bootzen/auth                  # Auth, Guards, AuthMiddleware
├── bootzen/view                  # Template engine con layouts
├── bootzen/console               # CLI commands (reemplaza make.sh)
├── bootzen/cache                 # Redis cache (extraído del core actual)
├── bootzen/components            # Alert, Button, Card, etc. (extraídos del core)
├── bootzen/cors                  # CorsMiddleware (extraído del core)
├── bootzen/logging               # Monolog integration
└── ...plugins de la comunidad
```

---

## Fase 0: Hardening del PHAR Core

**Objetivo**: Completar las piezas faltantes que DEBEN vivir en el PHAR para que
el framework funcione como dispatcher MVC básico.

### 0.1 — Base Controller

- **Crear**: `core/src/Core/Controller.php`
- Clase abstracta con conveniences: `json()`, `html()`, `redirect()`,
  `notFound()`
- Recibe `Application` via constructor injection (el Container ya soporta
  auto-wiring)
- NO incluye `$this->db`, `$this->cache`, `$this->view()` — esos los inyectan
  los plugins

### 0.2 — Sistema de Configuración

- **Crear**: `core/src/Core/Config.php`
- Carga archivos PHP de `config/` que retornan arrays
- Acceso dot-notation: `config('database.default')`
- Merge con `$_ENV` (env vars sobreescriben)
- Cache de configuración en producción (serializa a un solo archivo)
- **Modificar**: `core/src/Helpers/helpers.php` — agregar helpers `config()` y
  `app()`

### 0.3 — ServiceProvider Interface

- **Crear**: `core/src/Core/ServiceProviderInterface.php`
  ```php
  interface ServiceProviderInterface {
      public function register(Application $app): void;  // Container bindings
      public function boot(Application $app): void;      // Routes, middleware, events
  }
  ```
- **Crear**: `core/src/Core/ServiceProvider.php` — clase abstracta con
  implementaciones vacías por defecto

### 0.4 — Event Dispatcher Mínimo

- **Crear**: `core/src/Core/EventDispatcher.php`
- Solo string events + callables: `on($event, $listener)`,
  `dispatch($event, ...$args)`
- Eventos built-in: `app.booting`, `app.booted`, `request.received`,
  `response.sending`
- Sin event objects ni subscriber classes — es un microframework

### 0.5 — Plugin Loader (Auto-descubrimiento)

- **Crear**: `core/src/Core/PluginLoader.php`
- **3 estrategias de descubrimiento** (en orden de prioridad):
  1. **`config/plugins.php`** — lista explícita de providers (override manual)
  2. **Composer `extra.bootzen.providers`** — lee
     `vendor/composer/installed.json`
  3. **`plugins/` directory scan** — busca archivos `bootzen-plugin.php` que
     retornan `['providers' => [...]]`
- Ordenamiento topológico por dependencias (método opcional `dependencies()` en
  ServiceProvider)
- Lifecycle: discover → register ALL → boot ALL (dos pasadas separadas)

### 0.6 — Actualizar Application Boot Sequence

- **Modificar**: `core/src/Core/Application.php`
- Agregar `$basePath` property al constructor
- Nuevo boot sequence:
  1. Cargar Config desde `$basePath/config/`
  2. Registrar Config y EventDispatcher en Container
  3. Dispatch `app.booting`
  4. Plugin discovery via PluginLoader
  5. `register()` en todos los providers
  6. `boot()` en todos los providers
  7. Register error handlers
  8. Dispatch `app.booted`
  9. `$this->booted = true`

### 0.7 — Fix PHAR Stub

- **Modificar**: `core/stub.php`
- Eliminar `require 'phar://bootzen.phar/index.php'` (ese archivo NO existe)
- Reemplazar con `require 'phar://bootzen.phar/Helpers/helpers.php'`

### 0.8 — Extraer código que NO es core

- **Marcar para extracción** (no eliminar aún, se hace en Fase 2):
  - `Cache.php`, `CacheTenant.php` → futuro plugin `bootzen/cache`
  - `CorsMiddleware.php` → futuro plugin `bootzen/cors`
  - `Alert.php`, `Button.php`, `Card.php`, `Form.php`, `Input.php`,
    `Layout.php`, `Navigation.php` → futuro plugin `bootzen/components`
  - `HomeController.php`, `ApiRoutes.php` → eliminar del core (son ejemplos)

### Archivos a crear/modificar en Fase 0

| Archivo                                      | Acción                                       |
| -------------------------------------------- | -------------------------------------------- |
| `core/src/Core/Controller.php`               | Crear                                        |
| `core/src/Core/Config.php`                   | Crear                                        |
| `core/src/Core/ServiceProviderInterface.php` | Crear                                        |
| `core/src/Core/ServiceProvider.php`          | Crear                                        |
| `core/src/Core/EventDispatcher.php`          | Crear                                        |
| `core/src/Core/PluginLoader.php`             | Crear                                        |
| `core/src/Core/Application.php`              | Modificar (boot sequence, basePath)          |
| `core/src/Helpers/helpers.php`               | Modificar (agregar config(), app(), event()) |
| `core/stub.php`                              | Modificar (fix index.php → helpers.php)      |

---

## Fase 1: Arquitectura de Plugins

**Objetivo**: Definir el contrato completo de plugins, validar con un plugin de
referencia, y actualizar el scaffolding.

### 1.1 — Contrato de Plugin Composer

Cada plugin oficial sigue esta estructura:

```
bootzen-session/
  composer.json       # type: "bootzen-plugin", extra.bootzen.providers: [...]
  src/
    SessionServiceProvider.php
    Session.php
    SessionMiddleware.php
```

El `composer.json` del plugin:

```json
{
  "name": "bootzen/session",
  "type": "bootzen-plugin",
  "extra": {
    "bootzen": {
      "providers": ["BootZen\\Session\\SessionServiceProvider"]
    }
  },
  "autoload": { "psr-4": { "BootZen\\Session\\": "src/" } }
}
```

### 1.2 — Plugin local (sin Composer)

Para desarrollo rápido, un plugin puede vivir en `plugins/`:

```
plugins/mi-plugin/
  bootzen-plugin.php    # return ['providers' => [MiProvider::class]]
  src/
    MiProvider.php
```

### 1.3 — Plugin de Referencia: `bootzen/session`

- Primer plugin real para validar toda la arquitectura
- `SessionServiceProvider::register()` — bind Session en container
- `SessionServiceProvider::boot()` — registra SessionMiddleware como global
- `Session.php` — get/set/has/remove/flash/reflash, regeneración de ID
- `SessionMiddleware.php` — inicia sesión en cada web request
- Helper `session()` registrado en boot

### 1.4 — Agregar `composer.json` al repo del framework

- El repo actualmente NO tiene `composer.json`
- Crear uno con autoload PSR-4 para `BootZen\` → `core/src/`
- Dev dependencies: pestphp/pest, phpstan
- Esto permite que el framework sea testeable localmente

### 1.5 — Actualizar `init_project.sh`

- Generar directorio `config/` con archivos default: `app.php`, `database.php`,
  `plugins.php`
- Generar directorio `plugins/` vacío
- Actualizar `composer.json` del scaffold para incluir `bootzen/*` como
  dependencias
- Actualizar `public/index.php` para el nuevo boot sequence:
  ```php
  require __DIR__ . '/../vendor/autoload.php';
  if (file_exists(__DIR__ . '/../bootzen.phar')) {
      include_once __DIR__ . '/../bootzen.phar';
  }
  $app = new \BootZen\Core\Application(basePath: dirname(__DIR__));
  $app->handle();
  ```

### Entregables Fase 1

| Entregable                    | Ubicación                        |
| ----------------------------- | -------------------------------- |
| Plugin discovery funcional    | `core/src/Core/PluginLoader.php` |
| Plugin de referencia: session | Repo separado `bootzen/session`  |
| `composer.json` del framework | Raíz del repo                    |
| `init_project.sh` actualizado | Raíz del repo                    |

---

## Fase 2: Plugins Esenciales

**Objetivo**: Construir los plugins que cubren el 80% de los proyectos web. Cada
uno es un paquete Composer independiente.

### Matriz Core vs Plugin

| Feature                                                   | ¿En PHAR? | Razón                                                 |
| --------------------------------------------------------- | --------- | ----------------------------------------------------- |
| Application, Container, Router, Request, Response         | Sí        | Sin esto no hay framework                             |
| Base Controller, Config, ServiceProvider, EventDispatcher | Sí        | Infraestructura para plugins                          |
| PluginLoader, MiddlewareInterface, Component (abstracto)  | Sí        | Contratos y descubrimiento                            |
| helpers.php (subset core)                                 | Sí        | env, dd, config, app, url, e                          |
| Database/ORM                                              | Plugin    | Dep externa (PDO config), APIs pueden no necesitar DB |
| Session                                                   | Plugin    | APIs stateless no lo necesitan                        |
| Validation                                                | Plugin    | Complejo, evoluciona independientemente               |
| Auth                                                      | Plugin    | Depende de session + database                         |
| View engine                                               | Plugin    | Apps API-only no lo necesitan                         |
| CLI/Console                                               | Plugin    | No necesario en runtime                               |
| Cache Redis                                               | Plugin    | Requiere predis                                       |
| Componentes UI                                            | Plugin    | Son concerns de vista                                 |
| CORS                                                      | Plugin    | No todas las apps lo necesitan                        |
| Logging                                                   | Plugin    | Monolog es opcional                                   |

### Orden de construcción (por dependencias)

1. **`bootzen/database`** — Connection (PDO), QueryBuilder fluido, Model (Active
   Record), Migration, Schema, Seeder
2. **`bootzen/session`** — Session, flash messages, SessionMiddleware (ya creado
   en Fase 1)
3. **`bootzen/validation`** — Validator, reglas, `$request->validate()`,
   mensajes de error
4. **`bootzen/view`** — Template renderer con layouts, extends/yield/section,
   integración con Component
5. **`bootzen/csrf`** — CsrfMiddleware usando Session, integración con Form
   component
6. **`bootzen/auth`** — Auth manager, SessionGuard, AuthMiddleware,
   GuestMiddleware
7. **`bootzen/console`** — CLI commands, reemplaza `make.sh`, migrate, seed,
   route:list
8. **`bootzen/cache`** — Extraer Cache/CacheTenant del core, agregar FileCache
   como fallback
9. **`bootzen/cors`** — Extraer CorsMiddleware del core
10. **`bootzen/components`** — Extraer Alert, Button, Card, Form, Input, Layout,
    Navigation
11. **`bootzen/logging`** — Monolog integration, LogManager, helpers log\_\*

### Especificaciones clave de plugins prioritarios

**bootzen/database**:

- `Connection.php` — wrapper PDO, múltiples conexiones, reconnect, query logging
  en debug
- `QueryBuilder.php` — select/where/orderBy/join/insert/update/delete/paginate
- `Model.php` — Active Record: find, create, update, delete, relaciones básicas
  (hasMany, belongsTo), scopes, hooks
- `Migration.php` + `Schema.php` — sistema de migraciones con Schema Builder
- `DatabaseServiceProvider` — lee `config('database')`, bind Connection como
  singleton

**bootzen/validation**:

- Reglas: required, string, integer, email, url, min, max, in, regex, confirmed,
  unique, exists
- Validación anidada: `'items.*.price' => 'required|numeric'`
- Mensajes personalizables
- `$request->validate($rules)` retorna errores o datos validados

**bootzen/view**:

- Renderiza `.php` desde `views/`
- Layouts: `@extends('layouts.app')`, `@yield('content')`, `@section`
- Includes: `@include('partials.header')`
- Integración con Component system existente
- Helper `view('home.index', ['data' => $data])`

---

## Fase 3: Developer Experience

**Objetivo**: Hacer que desarrollar con BootZen sea productivo.

### 3.1 — CLI Tool (`bootzen/console`)

Comandos principales:

- `bootzen serve` — PHP built-in server
- `bootzen make:controller|model|middleware|service|view|migration|test Name`
- `bootzen migrate` / `migrate:rollback` / `migrate:status`
- `bootzen seed`
- `bootzen route:list` — tabla de rutas registradas
- `bootzen cache:clear` / `config:cache`
- `bootzen plugin:list` — plugins descubiertos y sus providers
- `bootzen make:plugin PluginName` — scaffold de plugin

### 3.2 — Actualizar Templates del Code Generator

- Que generen código usando las clases reales (Controller, Model, etc.)
- Que los tests generados usen PestPHP

### 3.3 — Error Pages y Debug Mode

- Debug mode: stack trace detallado con request info y lista de plugins
- Producción: páginas de error limpias desde `views/errors/`
- JSON errors para requests API (ya parcialmente implementado)

### 3.4 — Plugin Development Scaffold

- `bootzen make:plugin MiPlugin` genera estructura completa con `composer.json`,
  ServiceProvider, y `bootzen-plugin.php`

---

## Fase 4: Production Readiness

**Objetivo**: Framework confiable, seguro, y bien testeado.

### 4.1 — Tests del Framework

- Agregar `tests/` al repo del framework
- Tests unitarios: Container, Router, Request, Response, Config,
  EventDispatcher, PluginLoader, Controller
- Tests de integración: boot sequence, plugin discovery, middleware pipeline
- PestPHP + PHPUnit, SQLite en memoria para tests de DB
- Coverage objetivo: 70%

### 4.2 — Security Hardening

- Plugin `bootzen/security` — SecurityHeadersMiddleware (X-Content-Type-Options,
  X-Frame-Options, CSP, HSTS)
- Plugin `bootzen/rate-limit` — Rate limiting por IP
- Auditar helpers.php (csrf_token tiene lógica redundante)

### 4.3 — Performance

- Config caching: serializar a un solo PHP file
- Route caching: serializar tabla de rutas compiladas
- Lazy loading de ServiceProviders
- Medir overhead del PHAR boot

### 4.4 — CI/CD

- Ejecutar tests antes de build PHAR en GitHub Actions
- Matrix: PHP 8.2, 8.3, 8.4
- Publicar plugins a Packagist

### 4.5 — Documentación

- Getting Started, Plugin Development Guide, API Reference
- Proyectos ejemplo: blog (MVC completo), API (stateless), minimal (sin plugins)

---

## Grafo de Dependencias

```
Fase 0 (Core Hardening)
   │
   ▼
Fase 1 (Plugin Architecture)
   │
   ├──▶ Fase 2 (Plugins Esenciales) ─── construibles en paralelo
   │       │
   │       ▼
   └──▶ Fase 3 (Developer Experience) ─── depende de algunos plugins de Fase 2
           │
           ▼
        Fase 4 (Production Readiness) ─── depende de todas las fases previas
```

## Verificación

Para validar cada fase:

- **Fase 0**: Crear un proyecto con `init_project.sh`, verificar que
  `Application` arranca, carga config, y el PluginLoader descubre providers
  vacíos. El PHAR se construye sin errores.
- **Fase 1**: Instalar `bootzen/session` via Composer, verificar que se
  auto-descubre y la sesión funciona sin configuración manual.
- **Fase 2**: Crear proyecto completo con DB, auth, views, validación —
  formulario CRUD funcional end-to-end.
- **Fase 3**: `bootzen make:controller Post` genera controller correcto.
  `bootzen route:list` muestra rutas.
- **Fase 4**: `composer test` pasa con 70%+ coverage. CI green en PHP 8.2-8.4.
