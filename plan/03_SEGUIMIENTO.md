# Proceso de desarrollo

Seguimiento del [plan MVP monolito](04_PLAN-MVP-MONOLITO.md). Los planes 01 y
02 quedan como referencia histórica.

## Fase 0: Limpieza y reorganización

- [x] `composer.json` de desarrollo en la raíz con Pest, PHPStan y CS Fixer
- [x] Workflow `ci.yml` con PHPStan nivel 8 y tests en PHP 8.2, 8.3 y 8.4
      (pendiente confirmar la primera corrida en GitHub Actions)
- [x] Mover `core/src` a `framework/src` con autoloader propio en
      `framework/bootstrap.php`
- [x] Eliminar ejemplos del core (`HomeController`, `ApiRoutes`), Redis
      (`Cache`, `CacheTenant`), polyfills de aleatorios y `exit` en helpers
- [x] Carpeta `skeleton/` y `bin/bootzen-new`; `init_project.sh` como
      envoltorio
- [x] Retirar el `.phar` y publicar tarball en el release
- [x] Adaptar [docs/test-plans/core-framework.md](../docs/test-plans/core-framework.md)
      a la nueva estructura
- [x] `AGENTS.md` inicial del skeleton

Notas de cierre:

- `abort()` lanza `HttpException` y `Application` la convierte en respuesta;
  los manejadores globales de errores se registran solo en `handle()`, así
  `run()` no altera el estado de PHP en los tests.
- Los componentes de `framework/src/Components/` siguen como clases PHP hasta
  la fase 2. Perdieron la caché Redis.
- La carga de `.env` todavía no existe: `env()` lee variables del sistema
  hasta la fase 1.

## Fase 1: Núcleo HTTP sólido

- [x] `Env`, `Config`, `Logger` y `Cache` de archivo
- [x] Router reescrito (compilación, nombres, 405, grupos, `resource`)
- [x] Request y Response ampliados
- [x] `HttpException` y `ExceptionHandler` con vistas de error
- [x] Pipeline de middleware con alias y grupos
- [x] Cobertura de 90% en piezas críticas: Container 97.3%, Router 92.4%,
      Request 99.1%, Response 100% (total 90.7%). CI falla si el total baja
      de 80% (`pest --coverage --min=80`)

Notas de cierre:

- Los parámetros de ruta se inyectan por nombre y tipo con
  `Container::call()`; el formato `'Clase@metodo'` y el `__call` mágico de
  `Application` se eliminaron.
- El `OPTIONS` automático pasa por el middleware de la ruta, así `cors`
  funciona también como middleware de ruta.
- La caché de rutas se pospone a la fase 7 (`deploy:build`): las rutas con
  closures no se pueden serializar y el router ya compila cada patrón una
  sola vez. La caché de configuración existe (`Config::writeCache()`); su
  comando llega con la CLI en la fase 5.
- La caché es solo de archivos; APCu queda como mejora opcional.

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
