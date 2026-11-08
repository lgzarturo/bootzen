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

- [x] Compilador de vistas y componentes `<x-*>` (lista cerrada de
      directivas, slots, `@props`, componentes con clase, snapshots)
- [x] Migrar los 7 componentes actuales a vistas
- [x] Tailwind v4 standalone (`css:build`, `css:watch`)
- [x] Runtime `bootzen.js`

Notas de cierre:

- El JS vive directamente en `public/assets/js/` (sin copia desde
  `resources/js`): lo que se escribe es lo que se sirve, también por FTP.
- `bootzen.js` pesa ~9 KB sin minificar porque está documentado; la meta de
  ~3 KB del plan aplica al código efectivo. Se puede minificar en el
  `deploy:build` de la fase 7.
- Se agregó el helper `class_list()` en lugar de una directiva `@class`, para
  no ampliar la lista cerrada.
- `bin/bootzen` ya existe con los comandos de esta fase; la fase 5 lo amplía.
- CI compila el CSS con el binario real y falla si
  `skeleton/public/assets/app.css` quedó desactualizado.
- Verificado en Chromium: CSS aplicado, componente `data-component`, menú
  móvil y página 404.

## Fase 3: Datos

- [x] Connection, QueryBuilder y Model
- [x] Migraciones SQL, seeders y comandos de base de datos

Notas de cierre:

- Criterio cumplido: CRUD completo (rutas `resource`, modelo, vistas con
  `<x-pagination>`, `_method` y 404 de `findOrFail`) contra SQLite en memoria
  en `tests/Feature/CrudTest.php`.
- MySQL validado contra MySQL 8.4 real (`tests/Feature/MySqlTest.php`, job
  `mysql` en CI).
- Los modelos exigen `$table` explícita (pluralizar nombres en español falla:
  Ciudad → ciudads).
- `update()` y `delete()` sin `where` lanzan excepción a propósito.
- Sin carga anticipada de relaciones ni factories: quedan para cuando un
  caso real las pida (factories, con los generadores de la fase 5).
- `make:migration` y `make:model` llegan con los generadores de la fase 5.

## Fase 4: Sesión, seguridad y auth

- [x] Session, flash, `old()` y CSRF
- [x] Validator
- [x] Auth, SecurityHeaders y RateLimit

Notas de cierre:

- Criterio cumplido: registro, login, logout y formularios con errores de
  validación de punta a punta en `tests/Feature/AuthFlowTest.php` (framework)
  y `tests/Feature/AuthTest.php` (proyecto `--auth`), más un registro por HTTP
  real con sesiones en archivos en el job del skeleton.
- Sesión propia en lugar de `session_start()`: así la cookie viaja en el
  `Response` y los tests no necesitan trucos. Decisión tomada con el usuario.
- Recuperación de contraseña con un `Mailer` mínimo (drivers `log` y
  `array`); SMTP sigue en la fase 8.
- `getClientIp()` dejó de confiar en `X-Forwarded-For` cuando el cliente se
  conecta directo: con eso el límite de peticiones no se puede saltar.
- El correo es único sin distinguir mayúsculas (`COLLATE NOCASE` en la
  migración del scaffold); lo encontró el test de registro duplicado.
- Auditoría con el checklist de `docs/PROCESS.md` actualizada.
- Fuera de alcance: "recordarme", verificación de correo, roles y permisos.

## Fase 5: CLI y generadores

- [x] `bin/bootzen` con todos los comandos
- [x] `make:resource` con tests generados
- [x] `TestCase` de proyecto

Notas de cierre:

- Criterio cumplido: en CI, `make:resource Task title:string done:bool` en un
  proyecto recién creado pasa `composer check` sin tocar nada (y también
  `php bin/bootzen check`, `migrate`, `route:list` y `doctor`).
- Comandos nuevos: `make:resource`, `make:controller`, `make:model`,
  `make:migration`, `make:middleware`, `make:seeder`, `make:test`,
  `make:component`, `route:list`, `config:cache`, `config:clear`,
  `key:generate`, `doctor` y `check`.
- Plurales con reglas del inglés y `--table` para el resto (decisión del
  usuario). `--table` también nombra rutas y vistas.
- `route:cache` queda fuera (decisión del usuario): las rutas con closure no
  se pueden cachear y el router ya es rápido.
- En lugar de `$this->get()` (como decía el plan) los tests usan
  `client()->get()`: Pest tipa `$this` como `PHPUnit\Framework\TestCase` y
  PHPStan nivel 8 rechazaría `$this->get()` en los proyectos. Un objeto
  explícito también se sigue mejor con "ir a definición".
- `deploy:build` y `framework:update` se movieron a la fase 7, donde el plan
  los pone de nuevo junto a la guía de despliegue.
- Bugs encontrados al probar el generador por HTTP: la vista de edición no
  traía los valores, `back()` y la redirección por error de validación no
  usaban el `Referer` (ahora sí, solo si es del mismo sitio) y dos
  migraciones creadas en el mismo segundo podían compartir timestamp.

## Fase 6: Capa IA

- [x] `AGENTS.md`, recetas, referencia compacta y skills
- [x] App de demostración
- [x] Prueba de aceptación con agente (sección 5.3 del plan)

Notas de cierre:

- Prueba de aceptación hecha con un subagente aislado (decisión del usuario):
  un proyecto recién creado con `--auth` (el pedido 2 necesita login) y solo
  la documentación del proyecto como contexto. Completó los cuatro pedidos
  con `composer check` en verde al primer intento en cada uno (36 tests), sin
  leer ni tocar `framework/`. Verificado aparte en su proyecto.
- Sus siete fricciones se corrigieron: `make:controller --json` sin vista,
  props en inglés en `make:component`, aviso sobre clases nuevas de Tailwind
  y la red en la primera compilación, dónde van los helpers de test, cómo
  probar una ruta a mano, reemplazar `data-confirm` por el modal y un
  `--table` de más en la receta del CRUD.
- La referencia (`docs/ai/reference.md`) se genera desde los PHPDoc con
  `bin/bootzen-reference` y un test falla si queda vieja.
- Subida de archivos adelantada de la fase 8 en versión mínima
  (`UploadedFile`: `store`/`storePublic`), decisión del usuario, para que la
  receta no use `move_uploaded_file` a mano.
- Bug encontrado probando la demo en un navegador real: `asset()` armaba URLs
  con el host de `APP_URL`; en otro host o puerto la CSP bloqueaba el CSS y
  el JS. Ahora devuelve rutas relativas al dominio.
- App de demostración con `--demo` (auth + tareas por usuario, modal de
  borrado con `<dialog>`, JSON paginado y seeder), probada en Chromium.

## Fase 7: Despliegue y release v2.0.0

- [x] `deploy:build`
- [x] Docker, Caddy, Nginx y `.htaccess`
- [x] Guías probadas en hosting compartido, VPS y Docker
- [x] `framework:update`, CHANGELOG y guía de migración

Notas de cierre:

- Criterio de la fase (la app demo desplegada en los tres escenarios
  siguiendo solo la documentación): hecho con contenedores. Hosting
  compartido: Apache con la raíz en la carpeta del proyecto, artefacto
  descomprimido e instalador web; nada privado responde. VPS: `deploy.sh`
  por SSH a un servidor con PHP-FPM, primero con Nginx y después con Caddy;
  la sesión sobrevive a un despliegue, se respalda la base, se limpian
  versiones viejas y se vuelve atrás con el enlace. Docker: `docker compose`
  con SQLite y con MySQL, datos y sesiones después de reiniciar, vistas
  nuevas con el volumen viejo y `healthcheck` en verde. En los tres, un
  navegador se registró, creó una tarea, salió y volvió a entrar.
- Lo que encontraron esas pruebas y quedó corregido: las migraciones de
  `--auth` y `--demo` no corrían en MySQL; el contenedor migraba con la
  caché de configuración vieja del volumen; `doctor` daba "Todo listo" sin
  `APP_KEY` en producción, revisaba `database/` aunque la base estuviera en
  otra carpeta y pedía `.env` en Docker; el instalador no avisaba si no
  podía escribir su bloqueo; `deploy.sh` publicaba sin `APP_KEY` o con la
  base SQLite dentro de la versión.
- Decisiones: el instalador web es de un solo uso y está apagado por
  defecto; `framework:update` copia desde la instalación local con un
  manifiesto sha256 para detectar cambios a mano; PHP-FPM corre como el
  usuario de despliegue para que PHP y `bin/bootzen` no peleen por los
  permisos de SQLite; `config:cache` no va en el artefacto porque depende del
  `.env` del servidor.
- El CI despliega la demo en un Apache tipo hosting compartido y con
  `docker compose` (`tests/deploy/`). El de VPS necesita SSH y quedó como
  prueba manual.
- `VERSION` queda en 2.0.0; el tag `v2.0.0` se crea después del merge.

## Fase 8: Extras opcionales (post v2.0)

Un release por pieza (decisión del usuario).

- [x] Mail: SMTP propio con adjuntos (v2.1.0)
- [x] Colas en SQLite (y MySQL) con `queue:work --stop-when-empty` (v2.2.0)
- [x] Subida de archivos con discos (local, S3, FTP, SFTP) e imágenes (v2.3.0)
- [x] Scheduler (`schedule:run`) (v2.2.0)
- [x] i18n básico (v2.4.0)

Notas de SMTP (v2.1.0):

- Decisiones del usuario: alcance con adjuntos (además de cc, bcc, reply-to
  y remitente por mensaje); si el envío falla, `MailException` y cada flujo
  decide (el scaffold de `--auth` la registra y responde lo mismo);
  pruebas contra Mailpit en el CI.
- `SmtpMailer` usa sockets de PHP: STARTTLS (587), TLS directo (465) o sin
  cifrar, AUTH PLAIN o LOGIN según lo que anuncie el servidor, una conexión
  por correo. `MimeMessage` arma el MIME (quoted-printable, base64, asunto y
  nombres en RFC 2047, nombres de adjuntos en RFC 2231) y rechaza saltos de
  línea en cabeceras.
- Verificado: tests con un servidor SMTP falso (incluye STARTTLS y TLS con
  certificado generado al vuelo, y certificado rechazado si se verifica),
  `tests/Feature/SmtpTest.php` contra Mailpit real (adjuntos idénticos byte
  a byte, bcc solo en el sobre, línea con un punto), y a mano en un
  proyecto `--demo`: `mail:test`, recuperación de contraseña con el enlace
  que llega a Mailpit, y lo mismo desde el contenedor de Docker.
- `docker-compose.yml` ahora pasa el `.env` completo al contenedor
  (`env_file`), así llegan las variables de correo.

Notas de cola y scheduler (v2.2.0):

- Decisiones del usuario: la tabla se crea con una migración generada
  (`queue:table`); reintentos más tabla de fallidos con `queue:failed`,
  `queue:retry` y `queue:flush`; `mailer()->queue()`; cola y scheduler
  juntos en un release.
- Los jobs viajan como JSON con los parámetros públicos del constructor, sin
  `unserialize`. `sync` es el driver por defecto (y el de los tests), así
  `dispatch()` funciona sin tabla. La reserva de `database` es un `UPDATE`
  condicionado que vale igual en SQLite y MySQL.
- `schedule:run` recupera lo que tocaba entre dos vueltas del cron (sirve con
  hostings que solo permiten cron cada 5 o 15 minutos) y bloquea cada tarea
  para que no se encime consigo misma.
- Verificado: tests con reloj falso (reintentos, abandonados, dos workers,
  recuperación de minutos), MySQL 8.4 real, un contenedor con cron de
  Debian ejecutando la línea que imprime `schedule:list`, y `docker compose`
  con el servicio `scheduler` procesando jobs encolados desde la app.
- Lo que encontró la prueba con cron real: `php` a secas no existe para
  cron cuando PHP no está en `/usr/bin` (cron usa `PATH=/usr/bin:/bin`).
  `schedule:list` y `doctor` ahora dan la línea con la ruta completa
  (`PHP_BINARY`).

Notas de archivos (v2.3.0):

- Decisiones del usuario: drivers S3 compatible más FTP/SFTP; archivos
  privados con URL firmada temporal; procesamiento básico de imágenes con
  GD; prueba con servidor real en el CI.
- `store()` y `storePublic()` devuelven las mismas rutas que antes: el disco
  `public` tiene raíz `public/` y `storePublic()` guarda bajo `uploads/`, así
  `asset()` y las filas viejas siguen funcionando. Lo nuevo es `file_url()`,
  `temporary_url()` y `storage('disco')`.
- S3 sin SDK: firma SigV4 propia validada con los tres ejemplos de la
  documentación de AWS. SFTP necesita la extensión ssh2 (no hay forma sin
  librería ni extensión); verifica la huella del servidor si se configura.
- MinIO ya no publica imágenes en Docker Hub y la descarga del binario quedó
  bloqueada por permisos en la sesión, así que el servidor S3 de las pruebas
  es SeaweedFS (verifica la firma y permite lectura anónima por bucket).
- Lo que encontraron las pruebas reales: un FTP que no encierra al usuario en
  su carpeta (raíz relativa ahora parte de la carpeta del usuario); la
  orientación EXIF 5 y 7 tenía los giros invertidos; `encode()` dejaba el
  buffer de salida abierto con un formato inválido; los proyectos sin
  `config/storage.php` se quedaban sin discos; SeaweedFS reserva espacio por
  volumen y llenó el disco (el script de pruebas lo limita).

Notas de i18n (v2.4.0):

- Decisiones del usuario: el texto en español es la clave; idioma por
  `APP_LOCALE`, middleware opcional y prefijo en la URL pensando en SEO;
  español e inglés incluidos (framework y scaffolds); helpers básicos de
  fechas, números y moneda.
- El idioma por defecto no lleva prefijo, así las URLs de siempre no cambian
  y los proyectos viejos siguen igual. Las rutas no se traducen (`/en/tareas`),
  solo los textos; quedó anotado en la receta y en "Lo que todavía NO existe".
- `intl` es opcional: muchos hostings no la traen, así que `Formatter` tiene
  un formato propio para español e inglés.
- Lo que encontró la prueba real: `deploy:build` no copiaba `lang/` (la app
  desplegada se habría quedado en español); el 404 de `/en/lo-que-sea` salía
  en español porque ningún middleware corría; `make:resource` dejaba el CRUD
  fuera de `localized()` y las páginas en inglés enlazaban a la versión en
  español; `auth.home` era una ruta fija y mandaba al panel en español tras
  el login en inglés.
- Verificado: suite del repo, proyectos `--auth`, `--demo` y uno con
  `make:resource` con `bin/bootzen check`, y la demo en Chromium en
  `/en/...` (registro, crear tarea, modal, hreflang y 404).

## Fase 9: Usuarios, datos de prueba y administración (post v2.4)

Piezas elegidas por el usuario; un release por pieza. Detalle en el plan.

- [x] 9.1 Factories para tests y seeds (v2.5.0)
- [x] 9.2a Recordarme y verificación de correo (v2.6.0)
- [x] 9.2b Roles, permisos y políticas (v2.7.0)
- [ ] 9.3 URLs traducidas (v2.8.0)
- [ ] 9.4 Panel de administración con `make:admin` (v2.9.0)

Notas de factories (v2.5.0):

- Decisiones del usuario: `Tarea::factory()` por convención; métodos de
  `fake()` en inglés con datos en el idioma activo; semilla fija en tests;
  las factories viajan con `deploy:build`.
- `count()` devuelve un `FactoryBatch` aparte para que `create()` tenga un
  tipo claro (un modelo o una lista) y PHPStan no se queje en los proyectos.
- Lo que encontró la prueba real: las frases en español salían con errores
  de género ("con el reunión"); ahora el objeto lleva su artículo y el
  complemento va sin él.
- Verificado: suite del repo, proyecto `--demo` (24 tests y `db:seed` con 50
  tareas), y un proyecto limpio con `make:resource` de siete tipos de campo
  que pasa `bin/bootzen check`.

Notas de recordarme y verificación (v2.6.0):

- Decisiones del usuario: la 9.2 va en dos releases; la verificación se
  pide por ruta con `verified`; un solo rol por usuario; políticas por
  convención con el rol `'*'` pasando todas (estas tres últimas, para v2.7.0).
- "Recordarme" usa selector y validador con hash, rotación en cada uso y
  borrado de todos los tokens ante una copia robada. Las sesiones guardan
  una huella del hash de la contraseña: cambiarla cierra las demás, sin
  tablas extra.
- Los enlaces firmados firman el nombre de la ruta (sin prefijo de idioma) y
  sus parámetros, no la URL completa, para que sirvan detrás de proxies, en
  subcarpetas y en cualquier idioma.
- Lo que encontraron las pruebas: restablecer la contraseña cerraba las
  sesiones pero un "recordarme" robado seguía entrando (ahora se borran);
  PHPStan no veía los estados propios de una factory (ahora el modelo
  declara su factory con `@method`); el CSS precompilado quedó viejo con las
  vistas nuevas; un test de SMTP fallaba por una carrera al leer el log del
  servidor falso (reproducida retrasando el servidor y corregida).
- Verificado: suite del repo, proyectos `--auth` (19 tests) y `--demo` (28),
  el flujo HTTP del CI con el enlace sacado del log del correo, y Chromium
  con Mailpit real: registro, correo por SMTP, enlace, "recordarme" en un
  navegador nuevo y cerrar las demás sesiones.

Notas de roles, permisos y políticas (v2.7.0):

- Decisiones del usuario: todos empiezan como `usuario` y el primer admin se
  nombra con `user:role`; permisos con comodín (`tareas.*`); el demo usa
  `TareaPolicy`.
- `make:resource` no genera política (cambio frente al plan): sus CRUD no
  tienen dueño y una política de dueño los rompería. La receta
  `proteger-rutas.md` lleva el paso a paso con `make:policy`.
- `role` queda fuera de `$fillable` y un test del scaffold comprueba que el
  registro ignora `role=admin`.
- El demo sigue respondiendo 404 a tareas ajenas (`authorize(..., 404)`) para
  no revelar que existen; el admin las puede corregir.
- Verificado: suite del repo, proyectos `--auth` (20 tests) y `--demo` (30),
  `user:role` y `make:policy` desde la terminal, y MySQL 8.4 real.
