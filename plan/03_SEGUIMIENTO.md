# Proceso de desarrollo

Seguimiento del [plan MVP monolito](04_PLAN-MVP-MONOLITO.md). Los planes 01 y
02 quedan como referencia histórica.

## Fase 0: Limpieza y reorganización

- [ ] `composer.json` de desarrollo en la raíz con Pest, PHPStan y CS Fixer
- [ ] Workflow `ci.yml` con PHPStan nivel 8 y tests en PHP 8.2, 8.3 y 8.4
- [ ] Mover `core/src` a `framework/src` con autoloader propio en
      `framework/bootstrap.php`
- [ ] Eliminar ejemplos del core (`HomeController`, `ApiRoutes`), Redis
      (`Cache`, `CacheTenant`), polyfills de aleatorios y `exit` en helpers
- [ ] Carpeta `skeleton/` y `bin/bootzen-new`; `init_project.sh` como
      envoltorio
- [ ] Retirar el `.phar` y publicar tarball en el release
- [ ] Adaptar [docs/test-plans/core-framework.md](../docs/test-plans/core-framework.md)
      a la nueva estructura
- [ ] `AGENTS.md` inicial del skeleton

## Fase 1: Núcleo HTTP sólido

- [ ] `Env`, `Config`, `Logger` y `Cache` de archivo
- [ ] Router reescrito (compilación, nombres, 405, grupos, `resource`)
- [ ] Request y Response ampliados
- [ ] `HttpException` y `ExceptionHandler` con vistas de error
- [ ] Pipeline de middleware con alias y grupos

## Fase 2: Vistas, componentes y frontend

- [ ] Compilador de vistas y componentes `<x-*>`
- [ ] Migrar los 7 componentes actuales a vistas
- [ ] Tailwind v4 standalone (`css:build`, `css:watch`)
- [ ] Runtime `bootzen.js`

## Fase 3: Datos

- [ ] Connection, QueryBuilder y Model
- [ ] Migraciones SQL, seeders y comandos de base de datos

## Fase 4: Sesión, seguridad y auth

- [ ] Session, flash, `old()` y CSRF
- [ ] Validator
- [ ] Auth, SecurityHeaders y RateLimit

## Fase 5: CLI y generadores

- [ ] `bin/bootzen` con todos los comandos
- [ ] `make:resource` con tests generados
- [ ] `TestCase` de proyecto

## Fase 6: Capa IA

- [ ] `AGENTS.md`, recetas, referencia compacta y skills
- [ ] App de demostración
- [ ] Prueba de aceptación con agente (sección 5.3 del plan)

## Fase 7: Despliegue y release v2.0.0

- [ ] `deploy:build`
- [ ] Docker, Caddy, Nginx y `.htaccess`
- [ ] Guías probadas en hosting compartido, VPS y Docker
- [ ] `framework:update`, CHANGELOG y guía de migración
