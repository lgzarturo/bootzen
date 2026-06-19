# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

BootZen has two distinct purposes:

1. **Framework source** — `core/src/` contains the PHP framework (namespace `BootZen\`) packaged into `bootzen.phar`.
2. **Project generator** — `init_project.sh` scaffolds new PHP projects that _use_ the framework.

There is **no** `composer.json` or `package.json` at the repo root. Those files only exist inside projects scaffolded by `init_project.sh`.

## Build commands (repo root)

```bash
# Build the .phar (requires phar.readonly=0)
php -d phar.readonly=0 build-phar.php
```

CI (`release.yml`) builds the phar automatically on `v*` tag pushes using PHP 8.4.

## Scaffolded project commands

These run **inside** a project created by `init_project.sh`, not in this repo:

```bash
composer format        # php-cs-fixer fix
composer lint          # phpstan analyse src tests
composer test          # pest
composer check         # format → lint → test in order

# Dev server: Tailwind watch + PHP built-in server
npm run dev

# Code generators
composer make:controller
composer make:model
composer make:middleware
composer make:service
composer make:migration
composer make:seeder
```

Scaffolded projects also expose a `Makefile` with targets: `install`, `dev`, `test`, `format`, `lint`, `analyze`, `start`, `build`, `count`.

## Architecture

### Boot sequence

```
public/index.php → bootzen.phar (stub.php autoloader) → Application → Router → resolve()
```

`core/stub.php` registers the `BootZen\` PSR-4 autoloader pointing into `phar://bootzen.phar/src/` and runs the bootstrap.

### Core components (`core/src/Core/`)

- **Application** — front controller; owns the `Container` and `Router`; runs the global middleware chain then delegates to the router.
- **Router** — registers routes (`get`, `post`, `put`, `patch`, `delete`, `match`, `any`, `group`); resolves incoming requests; executes per-route middleware chain; dispatches handlers.
- **Container** — simple dependency injection container (`bind`, `singleton`, `instance`, `make`).
- **Request / Response** — HTTP abstractions; `Request::fromGlobals()` is the entry point; handlers may return a `Response`, a string (auto-wrapped as HTML), or an array (auto-wrapped as JSON).
- **Route** — holds method, path pattern, handler, middleware list, and extracted parameters.
- **Cache / CacheTenant** — Redis-backed caching helpers.
- **Component** — base class for UI components.

### Handler formats (Router)

```php
$router->get('/path', function (Request $req) { ... });           // Closure
$router->get('/path', 'ControllerClass@method');                   // string
$router->get('/path', [ControllerClass::class, 'method']);         // array
```

### Middleware

Implement `BootZen\Core\MiddlewareInterface`. Register globally via `$app->addGlobalMiddleware()` or per-route via `$route->middleware()`. `CorsMiddleware` handles OPTIONS preflight automatically through the router.

## Release workflow

- Version is tracked in the `VERSION` file (e.g., `1.1.0`).
- Pushing a tag like `v1.1.0` triggers `release.yml`, which builds `bootzen.phar` and publishes a GitHub Release with it attached.
- `install.sh` clones the repo to `~/.bootzen` and downloads the latest phar from GitHub Releases.
- `updater.sh` upgrades the local install (prefers release → tag → main).

## Key conventions

- PHP >=8.2; `declare(strict_types=1)` required in every file.
- PHPStan level 8 with bleeding-edge config.
- PSR-12 formatting enforced by PHP CS Fixer.
- Testing via Pest (not PHPUnit directly).
- Scaffolded projects: Husky + lint-staged pre-commit runs format → lint → test on `*.php` files.

## Commit conventions

Conventional Commits **in Spanish**, infinitive mood, no trailing period, header ≤ 69 chars:

```
feat(api): agregar endpoint de cotizaciones
fix(auth): corregir validación de sesión
docs(readme): actualizar instrucciones de instalación
chore(ci): actualizar workflow de release
```

Valid types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`, `ci`, `build`, `revert`.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
