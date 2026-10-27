# AGENTS.md

## What this repo is

BootZen is a boilerplate for building monolithic PHP MVPs (PHP + SQLite +
Tailwind + native JS + `<x-*>` view components), designed to be worked on with
generative AI. The repo has three parts:

1. **Framework source**: `framework/` (namespace `BootZen\`). It is copied as
   plain source code into every generated project. No `.phar`, no runtime
   Composer dependencies.
2. **Skeleton**: `skeleton/` is the boilerplate exactly as it lands in a new
   project (namespace `App\` for app code). Placeholders `__APP_NAME__` and
   `__APP_SLUG__` are replaced on creation.
3. **Installer**: `bin/bootzen-new` (PHP) copies `skeleton/` + `framework/`
   into a new directory. With `--auth` it also copies `stubs/auth/` on top
   (registration, login, logout and password reset); `--demo` adds
   `stubs/demo/` too (per-user task app with a delete modal and paginated
   JSON, the reference the AI can imitate). `init_project.sh` is a thin
   compatibility wrapper.

The roadmap lives in `plan/04_PLAN-MVP-MONOLITO.md`; progress is tracked in
`plan/03_SEGUIMIENTO.md`. Plans 01 and 02 are historical.

## Layout

- `framework/bootstrap.php`: own PSR-4 autoloader (`BootZen\` and `App\`),
  loads `vendor/autoload.php` only if present, then the helpers.
- `framework/src/Core/`: Application, Router, Route, Pipeline, Request,
  Response, Container, Env, Config, Logger, Cache, ExceptionHandler,
  HttpException, MiddlewareInterface, ParameterizedMiddleware.
- `framework/src/View/`: view engine (View, ViewCompiler with the closed
  directive list, ComponentAttributeBag, HtmlString, Component, ViewException).
- `framework/src/Database/`: data layer (Connection over PDO for SQLite and
  MySQL, QueryBuilder, Paginator, Model, ModelQuery, Migration, Migrator,
  Seeder, SeederRunner and their exceptions).
- `framework/src/Console/`: `bin/bootzen` commands (Console), generators
  (Generator, ResourceField) and `doctor` (Doctor).
- `framework/stubs/`: templates for `make:*` (`resource/` for
  `make:resource`). Placeholders look like `[[Model]]`.
- `framework/src/Testing/`: TestClient and TestResponse, the HTTP test
  helpers projects use through `client()`.
- `framework/src/Deploy/`: DeployBuilder (`deploy:build` artifact with
  precompiled views), WebInstaller (one-time `/_bootzen/instalar`, off unless
  `INSTALL_ENABLED=true`) and FrameworkUpdater (`framework:update` from
  `~/.bootzen`, sha256 `framework/MANIFEST.json` written by `bootzen-new`).
- `framework/src/Core/UploadedFile.php`: `$request->file()` with
  `store()`/`storePublic()` (random names, extension from the detected type).
- `framework/src/Support/Str.php`: name conversion and English plurals.
- `framework/src/Frontend/Tailwind.php`: Tailwind v4 standalone CLI download
  (SHA-256 verified) and build arguments.
- `framework/src/Session/`: Session, SessionStore, FileSessionStore and
  ArraySessionStore (own session, not PHP native sessions).
- `framework/src/Validation/`: Validator (closed rule list, Spanish
  messages) and ValidationException.
- `framework/src/Auth/`: Auth (session guard), Hash and PasswordReset.
- `framework/src/Security/RateLimiter.php` and `framework/src/Mail/`
  (Mailer with `log` and `array` drivers).
- `framework/src/Middleware/`: StartSession, VerifyCsrfToken,
  SecurityHeaders, RateLimit (`throttle:max,minutes`), Authenticate (`auth`),
  RedirectIfAuthenticated (`guest`) and CorsMiddleware.
- `framework/src/Helpers/helpers.php`: global helpers. None of them call
  `exit`: they return a `Response` or throw `HttpException`.
- `skeleton/`: `app/`, `bin/bootzen`, `bootstrap/app.php`, `config/`,
  `.env.example`, `routes/web.php` (group `web`), `routes/api.php` (group
  `api`), `public/` (with `assets/app.css` and
  `assets/js/`), `resources/views/` (components, errors), `resources/css/`,
  `storage/`, `database/`, `tests/`, `AGENTS.md`.
- Deploy files in the skeleton: root `.htaccess` (sends everything to
  `public/` on shared hosting), `Dockerfile`, `docker-compose.yml`,
  `.dockerignore`, `deploy/` (`nginx.conf`, `Caddyfile`, `php-fpm.conf`,
  `deploy.sh` and `docker/`) and the guides in `docs/deploy/`. They were
  tested by hand with the demo app on Apache, Nginx, Caddy and Docker; CI
  repeats shared hosting and Docker with `tests/deploy/*.sh`.
- `stubs/auth/`: files that `--auth` copies over the skeleton (controllers,
  User model, migration, views, `routes/auth.php`, its own `routes/web.php`,
  layout and `config/auth.php`, and `tests/Feature/AuthTest.php`).
- `stubs/demo/`: files that `--demo` copies after `stubs/auth/` (Tarea model
  and controller, views, `<x-modal>` + `modal.js`, seeder and tests).
- AI layer inside every project: `skeleton/AGENTS.md` (with `CLAUDE.md`
  importing it), `skeleton/docs/ai/recipes/*.md`, `skeleton/llms.txt` and the
  skills in `skeleton/.claude/skills/` mirrored in `skeleton/.agents/skills/`
  (a test checks both copies are identical and that doc links resolve).
- `skeleton/docs/ai/reference.md` is generated from the framework PHPDoc
  with `php bin/bootzen-reference`; a test fails if it is stale. Regenerate
  it after changing any public signature or its first PHPDoc line.
- `skeleton/public/assets/app.css` is generated by Tailwind from the views of
  a project created with `--demo`, so it also covers the auth and demo views, plus
  `framework/stubs` and `ResourceField.php`, so pages from `make:resource`
  look right before the first `css:build`. After
  changing classes in skeleton or stub views or JS, regenerate it
  (`php bin/bootzen css:build` in a project generated with `--demo` and copy
  it back); CI fails if it is stale.
- `tests/js/`: tests for `bootzen.js` with `node --test` and jsdom
  (`package.json` at the root is dev-only).
- `tests/`: Pest tests for the framework and the installer.

## Commands (repo root)

```bash
composer install     # dev tools only: Pest, PHPStan, PHP CS Fixer
composer check       # format:check -> phpstan (level 8) -> pest
composer format      # apply php-cs-fixer
composer test        # pest only
npm ci && npm run test:js            # bootzen.js tests (node --test + jsdom)

php bin/bootzen-new demo --no-git          # generate a project into ./demo
php bin/bootzen-new demo --auth --no-git   # same, with the auth scaffold
php bin/bootzen-new demo --demo --no-git   # auth + the demo task app
php bin/bootzen-reference                  # regenerate skeleton/docs/ai/reference.md
```

CI (`.github/workflows/ci.yml`) runs `composer check` on PHP 8.2, 8.3 and 8.4,
enforces at least 80% total coverage with pcov (`composer test:coverage`;
target for Container, Router, Request and Response is 90%), then generates a
project, checks it answers over `php -S` without `vendor/`, runs
`make:resource Task title:string done:bool` plus `composer check` and
`php bin/bootzen check` inside it (phase 5 criterion), `migrate`,
`route:list` and `doctor`, generates a second project with `--demo`, runs
`composer check` there, registers a user over real HTTP (file sessions and
CSRF), seeds it and reads the tasks as JSON, builds its CSS with the real
Tailwind binary (failing if
`skeleton/public/assets/app.css` is stale), builds a `deploy:build` artifact
and deploys it to an Apache container acting as shared hosting (web
installer included) and with `docker compose`, runs
`tests/Feature/MySqlTest.php` against a MySQL 8.4 service (skipped locally
unless `BOOTZEN_TEST_MYSQL_HOST` is set) and runs the `bootzen.js` tests with
Node 22.

## Release

- Version is tracked in `VERSION`. Tags follow `v{VERSION}`.
- Pushing a `v*` tag triggers `release.yml`: runs `composer check`, updates
  `CHANGELOG.md` and publishes `bootzen-<tag>.tar.gz` (framework, skeleton,
  stubs, bin, installer) as a GitHub Release.
- `install.sh` clones the repo to `~/.bootzen` and adds `~/.bootzen/bin` to the
  PATH. `updater.sh` moves the clone to the latest release, tag or main.
- Generated projects pull a newer framework with `php bin/bootzen
  framework:update`, which copies `framework/` from that clone
  (`BOOTZEN_HOME` or `~/.bootzen`, or `--from=path`), refuses downgrades and
  local edits without `--force`, and leaves a backup in `storage/backups/`.

## Key conventions

- PHP >= 8.2, `declare(strict_types=1)` in every file.
- Zero Composer `require` dependencies in the framework and the skeleton.
  Dev tools go in `require-dev` only.
- PHPStan level 8 with bleeding edge config. PSR-12 via PHP CS Fixer.
- Pest for tests. Every framework change ships with tests.
- When the framework API changes, update `skeleton/AGENTS.md` (it is the AI
  guide inside every generated project), including its "not available yet"
  list.

## Commit conventions

Conventional Commits **in Spanish**, infinitive mood, no trailing period,
lowercase first word, header up to 69 chars:

```
feat(api): agregar endpoint de cotizaciones
fix(auth): corregir validación de sesión
chore(ci): actualizar workflow de release
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`,
`ci`, `build`, `revert`.
