# AGENTS.md

## What this repo is

BootZen is a PHP microframework **and** a project scaffolding tool. The repo
serves two purposes:

1. **Framework source** — `core/src/` contains the framework code (namespace
   `BootZen\`) that gets packaged into `bootzen.phar`.
2. **Project generator** — `init_project.sh` scaffolds a new PHP project with
   its own `composer.json`, `package.json`, Makefile, etc. The scaffolded
   project is what end users develop in.

There is **no** `composer.json` or `package.json` at the repo root. Those files
only exist inside scaffolded projects.

## Architecture

- `core/src/Core/` — Application, Router, Request, Response, Container, Route,
  Cache, Component
- `core/src/Controllers/` — HomeController, ApiRoutes (default route
  registrations)
- `core/src/Components/` — UI components (Alert, Button, Card, Form, Input,
  Layout, Navigation)
- `core/src/Middleware/` — CorsMiddleware
- `core/src/Helpers/` — Global helper functions
- `core/stub.php` — Phar autoloader/bootstrap entry point
- `build-phar.php` — Builds `bootzen.phar` from `core/src/` + `core/stub.php`
- `bin/` — Shell helper scripts copied into scaffolded projects (start.sh,
  make.sh, analyzer.sh, count_lines.sh)

## Phar build & release

- `build-phar.php` packages everything in `core/src/` into `bootzen.phar` using
  `core/stub.php` as the stub.
- CI (`.github/workflows/release.yml`) triggers on `v*` tags: builds the phar
  with PHP 8.4, updates CHANGELOG.md, creates a GitHub Release with the phar
  attached.
- `install.sh` clones the repo to `~/.bootzen` and downloads the latest phar
  from GitHub Releases.
- `updater.sh` updates the local `~/.bootzen` clone (prefers release → tag →
  main).

## Scaffolded project commands

These commands run **inside** a project created by `init_project.sh`, not in
this repo:

```bash
# Check pipeline (order matters)
composer format        # php-cs-fixer fix
composer lint          # phpstan analyse src tests
composer test          # pest

# Shortcut: runs all three in order
composer check

# Dev server (Tailwind watch + PHP built-in server)
npm run dev

# Production CSS build
npm run build

# Code generators (inside scaffolded project)
composer make:controller  # php .scripts/make.php controller
composer make:model
composer make:middleware
composer make:service
composer make:migration
composer make:seeder
```

Scaffolded projects also have a `Makefile` with: `make install`, `make dev`,
`make test`, `make format`, `make lint`, `make analyze`, `make start`,
`make build`, `make count`.

## Commit conventions

Conventional Commits **in Spanish**, infinitive mood, no trailing period,
lowercase first word:

```
feat(api): agregar endpoint de cotizaciones
fix(auth): corregir validación de sesión
refactor(db): optimizar consulta de habitaciones
chore(config): actualizar script de deploy
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

## Key conventions

- PHP >=8.2, `declare(strict_types=1)` enforced
- PHPStan level 8 with bleeding edge config
- PHP CS Fixer with PSR-12+ rules (see `.php-cs-fixer.php` in scaffolded
  projects)
- Pest for testing (not PHPUnit directly)
- Tailwind CSS for frontend (with `@tailwindcss/forms` and
  `@tailwindcss/typography` plugins)
- PSR-4 autoloading: namespace `BootZen\` → `src/` in scaffolded projects
- Scaffolded projects use Husky + lint-staged pre-commit hooks (format → lint →
  test on `*.php` files)
- `public/index.php` is the front controller; it loads `bootzen.phar` if
  present, then Composer autoloader, then Boots the Application

## Version

Current version is tracked in `VERSION` file (currently `1.1.0`). Tags follow
`v{VERSION}` format (e.g., `v1.1.0`).
