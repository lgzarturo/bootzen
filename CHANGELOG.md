# CHANGELOG

## [2.10.0] - 2026-11-13

Versión de seguridad: fase A de la auditoría
(`plan/05_AUDITORIA-SEGURIDAD-Y-RENDIMIENTO.md`). Conviene actualizar.

### Seguridad

- Los límites de intentos ya no se saltan con un `X-Forwarded-For`
  inventado. `getClientIp()` devuelve la IP de la conexión y solo lee
  `X-Forwarded-For` si viene de un proxy de confianza; en ese caso recorre la
  cadena desde el final y se queda con la primera IP que no es un proxy.
  `isSecure()` aplica la misma regla a `X-Forwarded-Proto`, y el helper
  `get_client_ip()` usa la misma lógica.
- Contadores atómicos: `Cache::increment()` toma un candado, y
  `RateLimiter::attempt()` revisa y cuenta en un solo paso. Con 40 logins en
  paralelo contra un límite de 5 antes pasaban 13; ahora pasan 5.
- Scaffold `--auth`: `throttle` en registro (5 cada 10 minutos por IP), login
  y restablecer contraseña; el login cuenta el intento antes de verificar la
  contraseña y suma un tope por cuenta (30 por hora desde cualquier IP).
- Las respuestas con sesión salen con `Cache-Control: private, no-store`,
  salvo que la ruta ponga el suyo.
- Permiso 600 para `.env` (`bootzen-new` y `key:generate`), sesiones, caché,
  logs y la base SQLite al crearla. El archivo de sesión lleva el hash del
  id, no el id.
- La caché en archivo se firma con `APP_KEY`: un archivo sin firma válida se
  descarta sin deserializarlo.
- El CSRF solo se omite con `APP_ENV=testing` en CLI (los tests), no en un
  servidor web.
- `CorsMiddleware` agrega `Vary: Origin` al reflejar el origen.
- El log sangra las líneas siguientes de un mensaje, así no se pueden fingir
  entradas.

### Agregado

- `config/security.php`: `trusted_proxies` (variable `TRUSTED_PROXIES`):
  IPs, rangos CIDR (IPv4 e IPv6), `private` o `*`.
  `Request::setTrustedProxies()`.
- `throttle:max,minutos,nombre`: el tercer parámetro da cupo propio a la
  ruta.
- `Cache::atomic($clave, fn)` y `RateLimiter::attempt()`.
- `config/cors.php` para configurar el alias `cors`.
- `doctor` avisa de `APP_ENV=testing`, de `.env` o base SQLite legibles por
  otros usuarios, de FTP sin cifrar y de SFTP sin huella.

### Al actualizar

- Si la app está detrás de un proxy, balanceador o CDN, define
  `TRUSTED_PROXIES` (ver `docs/deploy/README.md`). Sin eso la app ve la IP
  del proxy y las cookies no salen `Secure`. El `docker-compose.yml` nuevo
  trae `private` por defecto; en proyectos existentes agrégalo a mano, junto
  con la línea `'trusted_proxies' => env('TRUSTED_PROXIES', '')` en
  `config/security.php`.
- Las sesiones abiertas y la caché en archivo se pierden una vez (cambió el
  nombre del archivo de sesión y el formato de la caché): los usuarios
  vuelven a iniciar sesión. Los tokens de "recordarme" siguen valiendo.
- Los archivos nuevos se crean con permiso 600. Si el servidor web y la
  terminal usan usuarios distintos, deben ser el mismo (como en las guías) o
  compartir los archivos por otro medio.
- Los cambios del scaffold `--auth` (rutas y `LoginController`) solo llegan a
  proyectos nuevos; en uno existente cópialos de `stubs/auth`.

## [2.9.0] - 2026-11-08

Panel de administración (fase 9.4), la última pieza de la fase 9.

### Agregado

- `make:admin Producto nombre:string precio:float` (proyectos `--auth`):
  controlador en `app/Controllers/Admin`, vistas con el layout
  `<x-layouts.admin>` y barra lateral (`config/admin.php`), rutas en un grupo
  `/admin` con `['auth', 'verified', 'role:admin']` y su test. Si el modelo
  no existe, crea también la migración, el modelo y la factory.
- `BootZen\Admin\Listing`: búsqueda (`?q=`), orden por columnas de una lista
  blanca, filtros booleanos y por rango de fechas, paginación que conserva
  todo en los enlaces y exportación a CSV en streaming (BOM para Excel y
  protección contra fórmulas).
- `whereLike()` en el query builder y en los modelos (los `%` y `_` del
  usuario se buscan literales, igual en SQLite y MySQL) y `cursor()` para
  recorrer tablas grandes por bloques.
- `Response::stream()` y el helper `current_path()`.
- Demo: panel con usuarios (buscar, filtrar por rol, cambiar rol, marcar
  verificado; un admin no puede quitarse su propio rol) y tareas de todos
  los usuarios con su dueño. Enlace "Administración" solo para admins.

### Cambiado

- Los booleanos en las vistas de `make:resource` usan `__('Sí')` y
  `__('No')`.

## [2.8.0] - 2026-11-08

URLs traducidas (fase 9.3): las páginas en inglés ya no viven en
`/en/tareas` sino en `/en/tasks`.

### Agregado

- `lang/{idioma}/rutas.php` traduce los segmentos fijos de las rutas de
  `$router->localized()` (`'tareas' => 'tasks'`); los parámetros no cambian.
  Se escriben las rutas una sola vez en español.
- La URL sin traducir (`/en/tareas/7?x=1`) redirige con 301 a la traducida,
  con parámetros y query.
- Si dos rutas quedan en la misma URL al traducir, la app lo dice al
  arrancar y nombra el archivo a revisar.
- `Translator::routeSegments()`; `rutas.php` se carga aparte de los textos.
- Scaffold `--auth` y demo con URLs en inglés: `/en/sign-up`,
  `/en/forgot-password`, `/en/verify-email`, `/en/dashboard`,
  `/en/tasks/{id}/toggle`. `skeleton/lang/en/rutas.php` de ejemplo.

### Cambiado

- `route()`, `hreflang_links()`, `locale_url()` y `signed_route()` dan la
  URL traducida sin cambios en el código que los usa.
- En proyectos creados antes no cambia nada hasta crear `lang/en/rutas.php`.

## [2.7.0] - 2026-11-08

Segunda mitad de la autenticación completa (fase 9.2): roles, permisos y
políticas.

### Agregado

- Roles con sus permisos en `config/auth.php` (`'admin' => ['*']`,
  `'editor' => ['tareas.*', 'reportes.ver']`). `can('reportes.ver')`,
  middleware `role:admin,editor` y `can:reportes.ver`.
- Políticas por convención: `App\Policies\TareaPolicy` para
  `App\Models\Tarea`, con métodos como `update(User $user, Tarea $tarea)`.
  `can('update', $tarea)`, `authorize('update', $tarea)` (403, o 404 para no
  revelar que el registro existe) y `can('create', Tarea::class)`. Un rol con
  `'*'` pasa todas.
- Trait `BootZen\Auth\Authorizable` para el modelo de usuario:
  `$user->can()` y `$user->hasRole()`.
- `make:policy Modelo` y `user:role correo rol` (para nombrar al primer
  admin, valida que el rol exista).
- Scaffold `--auth`: columna `role` (todos empiezan como `usuario`, fuera de
  `$fillable`), roles en `config/auth.php` y `UserFactory::admin()`.
- Demo: `TareaPolicy`; el controlador usa `authorize(..., 404)` y la lista
  muestra editar y borrar con `can()`. El admin puede corregir tareas ajenas.
- PHPStan del repo analiza también `stubs/auth/app` y sus factories.

### Cambiado

- Proyectos `--auth` existentes: copia la migración
  `2025_01_03_000000_agregar_rol_a_usuarios.php`, corre `migrate`, agrega
  `roles` a `config/auth.php` y `use Authorizable;` al modelo `User`.

## [2.6.0] - 2026-11-08

Primera mitad de la autenticación completa (fase 9.2): "recordarme",
verificación de correo y enlaces firmados.

### Agregado

- "Recordarme": `auth()->attempt($credenciales, $remember)` y
  `auth()->login($usuario, true)`. Un token por dispositivo en
  `remember_tokens` con selector y validador (solo se guarda el hash), que se
  renueva en cada uso; si aparece una copia robada ya usada, se borran todos
  los tokens del usuario. Middleware `RememberMe` en el grupo `web`.
- Cambiar la contraseña cierra solas las demás sesiones (cada sesión guarda
  una huella del hash). `auth()->logoutOtherDevices($password)` y
  `auth()->forgetRememberedDevices($usuario)`.
- Enlaces firmados: `signed_route('nombre', $parametros, $minutos)` y el
  middleware `signed`. Firman el nombre de la ruta y sus parámetros con la
  `APP_KEY`, así sirven detrás de un proxy, en una subcarpeta y en `/en/`.
- Middleware `verified` (columna `email_verified_at`): manda a
  `verification.notice` o responde 403 en JSON.
- Scaffold `--auth`: casilla "Recordarme", correo de verificación al
  registrarse (`App\Mail\VerificationEmail`), página "verifica tu correo"
  con reenvío limitado, enlace firmado que funciona sin sesión, panel y
  tareas con `['auth', 'verified']`, y "cerrar sesión en los demás
  dispositivos" en el panel. Restablecer la contraseña verifica el correo y
  borra los "recordarme". Todo traducido al inglés.
- `UserFactory` con `email_verified_at` y el estado `sinVerificar()`.
  `make:resource` agrega `@method static ...Factory factory()` al modelo
  para que PHPStan vea los estados propios.
- `TestClient::closeBrowser()` (olvida la cookie de sesión y conserva la de
  "recordarme") y `cookies()`.

### Cambiado

- El grupo `web` del skeleton es `[StartSession, RememberMe, VerifyCsrfToken]`.
  Sin cookie de "recordarme" no toca la base. En proyectos creados antes,
  agrega `RememberMe::class` en `bootstrap/app.php` si usas "recordarme".
- Proyectos `--auth` existentes: copia la migración
  `2025_01_02_000000_recordarme_y_verificacion.php` y corre `migrate`; los
  usuarios ya registrados quedan sin verificar (márcalos con
  `UPDATE users SET email_verified_at = created_at` si no quieres pedírselo).

### Corregido

- Un test del correo SMTP fallaba a veces con la suite en paralelo: el
  cliente cierra sin esperar la respuesta a `QUIT` y el test leía el log del
  servidor falso antes de que terminara de escribirlo.

## [2.5.0] - 2026-11-08

Primera pieza de la fase 9: factories para tests y seeders, con datos falsos
propios y sin librerías.

### Agregado

- `Modelo::factory()` con factories en `database/factories/`
  (`Database\Factories\TareaFactory`): `create()`, `make()`,
  `count(20)->create()`, `state([...])` o `state(fn ($valores, $i) => ...)`,
  `afterCreating()` y relaciones con otra factory
  (`'user_id' => User::factory()`). Los lotes se guardan en una transacción.
- `fake()`: nombres, correos (en dominios `example.*`), teléfonos,
  empresas, direcciones, frases, párrafos, números, decimales, fechas,
  `randomElement()`, `uuid()` y `unique()`, en español o inglés según el
  idioma activo. En tests la semilla es fija.
- `make:factory Modelo`. `make:resource` genera la factory con un valor falso
  por campo y su test la usa.
- `--auth` trae `UserFactory`; `--demo` trae `TareaFactory` y su seeder
  crea 50 tareas.

### Cambiado

- `deploy:build` incluye `database/factories/` (los seeders pueden usarlas
  en el servidor).
- El autoloader del framework y el `composer.json` del skeleton cargan
  `Database\Factories\`. En proyectos creados antes, `framework:update`
  trae el autoloader; agrega la línea a `composer.json` si usas PHPStan con
  factories.

## [2.4.0] - 2026-11-07

Última pieza de la fase 8: la app en varios idiomas, con URLs pensadas para
buscadores y formatos de cada país, sin librerías.

### Agregado

- `__('Texto en español')`: el español es la clave y `lang/en.php` (o
  `lang/en/*.php`) tiene la traducción. Marcadores (`:nombre`), plurales con
  `trans_choice('uno|:count varios', $n)` y, sin traducción, sale el texto
  en español.
- `APP_LOCALE` (idioma por defecto) y `APP_LOCALES` (los que acepta la app).
- `$router->localized()`: cada página existe en cada idioma, la del idioma
  por defecto sin prefijo (`/precios`) y las demás con el suyo
  (`/en/precios`). `route()` elige la del idioma activo, así enlaces y
  redirecciones se quedan en el idioma del usuario.
- `hreflang_links()` con las URLs completas de cada idioma y `x-default`,
  `locale()`, `locale_url('en')` para cambiar de idioma.
- Middleware `locale`: `locale:en` fija el idioma; sin parámetro usa
  `?lang=`, la sesión, el navegador y al final `APP_LOCALE`.
- `format_date()`, `format_number()` y `format_money()` (`APP_CURRENCY`)
  con `intl` si está instalada y un formato propio para español e inglés si
  no.
- Inglés incluido: mensajes de validación, errores, paginación, la página
  de bienvenida, las páginas de error, `make:resource` y los scaffolds
  `--auth` y `--demo` (con sus tests en `/en/...`).
- `home_url()`: `auth.home` acepta un nombre de ruta (`'dashboard'`), que
  respeta el idioma.

### Cambiado

- Las rutas de páginas del skeleton, `--auth` y `--demo` van dentro de
  `$router->localized()`; las de siempre no cambian (`/panel`, `/tareas`).
- `make:resource` agrega su ruta dentro de `localized()` si existe.
- Una URL con prefijo de idioma que no existe (`/en/nada`) responde el 404
  en ese idioma.
- `deploy:build` incluye la carpeta `lang/`.
- En proyectos creados antes no cambia nada: sin `lang/` ni `localized()`
  todo sigue en español. Para sumar idiomas, copia `config/app.php` (claves
  `locales` y `currency`) y sigue `docs/ai/recipes/idiomas.md`.

## [2.3.0] - 2026-11-07

Tercera pieza de la fase 8: archivos en cualquier destino y procesamiento de
imágenes, sin librerías.

### Agregado

- Discos con nombre en `config/storage.php`: `local`, `s3` (AWS S3 y
  compatibles: Cloudflare R2, DigitalOcean Spaces, Backblaze B2, Wasabi,
  MinIO, Google Cloud Storage interoperable), `ftp` (FTP y FTPS) y `sftp`.
  Cada disco es público o privado y puede tener un prefijo.
- Toda la app cambia de destino desde `.env`: `STORAGE_DISK` (privados) y
  `STORAGE_PUBLIC_DISK` (públicos).
- `storage('disco')` con `put()`, `putFile()`, `get()`, `exists()`,
  `delete()`, `size()`, `url()`, `temporaryUrl()` y `download()`;
  helpers `file_url()` y `temporary_url()`.
- URLs temporales para archivos privados: prefirmadas en S3 y, en los demás
  discos, por `/_bootzen/archivo` firmada con la `APP_KEY`.
- `store()` y `storePublic()` aceptan el disco: `store('contratos', 's3')`.
- Imágenes con GD: `$request->file('foto')->resize(1600)` y
  `->cover(300, 300)`, que aplican la orientación de la cámara y quitan los
  metadatos EXIF (incluido el GPS). Clase `BootZen\Support\Image`.
- `doctor` prueba la conexión de los discos por defecto.
- El `Dockerfile` instala `gd`, `exif` y `ftp`.
- CI: job con servidores reales de S3 (SeaweedFS), FTPS y SFTP.

### Cambiado

- `store()` y `storePublic()` guardan a través de los discos; las rutas que
  devuelven no cambian (`uploads/...` para públicos), así las filas
  existentes y `asset()` siguen funcionando.
- Los discos públicos rechazan siempre archivos que el navegador o el
  servidor ejecutarían (php, html, svg, js, `.htaccess`).
- En proyectos creados antes, copia `config/storage.php` del skeleton para
  configurar discos remotos (sin él siguen funcionando `local` y `public`).

## [2.2.0] - 2026-11-07

Segunda pieza de la fase 8: cola de trabajos y tareas programadas, con el
cron como único motor y sin librerías.

### Agregado

- Cola de trabajos: `dispatch(new MiJob($id))` y `make:job`. Los jobs
  guardan como JSON los parámetros públicos de su constructor, reciben
  dependencias del contenedor en `handle()` y definen `$tries`, `$backoff`
  y `failed()`.
- Drivers `sync` (por defecto: ejecuta en el momento, sin tabla) y
  `database` (SQLite o MySQL; tablas con `queue:table` + `migrate`).
- `queue:work` (`--stop-when-empty`, `--max-jobs`, `--max-time`, `--queue`,
  `--sleep`), `queue:failed`, `queue:retry <id|all>` y `queue:flush`. Un
  job abandonado por un worker caído vuelve a la cola tras
  `QUEUE_RETRY_AFTER` segundos.
- `mailer()->queue($message)` envía el correo en segundo plano, adjuntos
  incluidos.
- Tareas programadas en `routes/schedule.php`: `command()`, `job()` y
  `call()` con `everyMinute()`, `dailyAt('03:00')`, `weekdaysAt()`,
  `cron('...')` y más. `schedule:run` (desde una sola línea de cron)
  recupera lo que tocaba entre vueltas si el cron corre cada 5 o 15
  minutos y no deja que una tarea se encime consigo misma.
  `schedule:list` muestra la próxima ejecución y la línea de cron con la
  ruta completa de PHP.
- Docker: servicio `scheduler` en `docker-compose.yml` que hace de cron.
- `doctor` avisa si hay tareas y el cron no corre, si falta la tabla de la
  cola o si hay jobs fallidos.
- Recetas `cola-de-trabajos` y `tareas-programadas`; guías de despliegue
  con el cron de cada escenario.

### Cambiado

- `mailer()` devuelve un `MailService` (implementa `Mailer` y suma
  `queue()`). El código que usa `send()` no cambia.
- En proyectos creados antes, copia del skeleton `config/queue.php`,
  `routes/schedule.php`, `docker-compose.yml`, `deploy/docker/` y el
  `Dockerfile` después de `framework:update`.

## [2.1.0] - 2026-11-07

Primera pieza de la fase 8: envío de correo real por SMTP, sin librerías.

### Agregado

- Driver `smtp` (`MAIL_DRIVER=smtp`): STARTTLS (puerto 587), TLS directo
  (465) o sin cifrar para servidores locales, autenticación PLAIN o LOGIN y
  verificación del certificado (`MAIL_VERIFY_PEER`). Se configura con
  `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`,
  `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME`.
- `Message` suma `cc()`, `bcc()`, `replyTo()`, `from()`, `attach($ruta)` y
  `attachData($contenido, $nombre)`, encadenables. El constructor de
  siempre no cambia. Asunto, nombres y adjuntos en UTF-8.
- `MailException` cuando un correo no se puede armar o enviar. El mensaje
  trae la respuesta del servidor y nunca la contraseña.
- `php bin/bootzen mail:test tu@correo.com` para probar la configuración.
- `doctor` revisa la configuración de SMTP y avisa si en producción los
  correos siguen yendo al log.
- CI: job con Mailpit que exige STARTTLS y autenticación.

### Cambiado

- La recuperación de contraseña de `--auth` atrapa `MailException`, la
  registra en el log y responde lo mismo, sin revelar si la cuenta existe.
- `docker-compose.yml` pasa todo el `.env` al contenedor (`env_file`) y
  sigue forzando los valores de producción.
- `config/mail.php` y `.env.example` traen el remitente y las variables de
  SMTP. En proyectos creados con la 2.0, copia `config/mail.php` del
  skeleton después de `framework:update`.

## [2.0.0] - 2026-10-27

Primera versión estable. BootZen pasa de microframework en `.phar` a
boilerplate de monolitos para MVP: PHP 8.2+, SQLite, Tailwind v4 sin Node,
JavaScript nativo y vistas con componentes `<x-*>`, con el framework como
código fuente dentro de cada proyecto y sin dependencias de Composer en
producción. Pensado para construirse con IA generativa y desplegarse en
hosting compartido, VPS o Docker. Detalle por fase abajo; el plan está en
[plan/04_PLAN-MVP-MONOLITO.md](plan/04_PLAN-MVP-MONOLITO.md).

**Cambios incompatibles con la v1.x.** Cómo pasar un proyecto:
[docs/MIGRACION-V1-V2.md](docs/MIGRACION-V1-V2.md).

### Fase 7: despliegue

#### Agregado

- `php bin/bootzen deploy:build [--version=] [--no-css]`: arma
  `build/<app>-<versión>/` y su `.zip` (o `.tar.gz`) sin `.env`, base,
  tests ni `vendor/`, con el CSS compilado y las vistas precompiladas.
- Instalador web de un solo uso para hostings sin terminal:
  `INSTALL_ENABLED=true` habilita `/_bootzen/instalar`, que pide la
  `APP_KEY`, ejecuta las migraciones y se bloquea solo
  (`storage/install.lock`). Límite de 5 intentos cada 10 minutos.
- `php bin/bootzen framework:update [--from=] [--force]`: copia
  `framework/` desde la instalación local (`BOOTZEN_HOME` o `~/.bootzen`),
  no baja de versión ni pisa cambios locales sin `--force` (los detecta con
  `framework/MANIFEST.json`) y deja un respaldo en `storage/backups/`.
- En cada proyecto: `.htaccess` en la raíz para hostings donde no puedes
  apuntar a `public/`, `Dockerfile` y `docker-compose.yml` (Apache, OPcache,
  volúmenes para datos y archivos), y en `deploy/` las configuraciones de
  Nginx, Caddy y un pool de PHP-FPM, más `deploy.sh` para VPS con
  `releases/`, `shared/` y vuelta atrás.
- Guías en `docs/deploy/` del proyecto (hosting compartido, VPS y Docker),
  probadas con la app demo en Apache, Nginx, Caddy y Docker con SQLite y
  MySQL. El CI repite hosting compartido y Docker en cada cambio.
- Guía de migración desde la v1 en `docs/MIGRACION-V1-V2.md`.

#### Cambiado

- Las vistas compiladas se nombran según la ruta relativa al proyecto, así
  las precompiladas en `deploy:build` sirven en el servidor.
- `doctor`: `APP_KEY` vacía en producción es un error, revisa la carpeta
  real de la base SQLite, acepta variables del servidor sin `.env` y avisa
  si el instalador web sigue activo.
- `release.yml` usa la sección escrita a mano del CHANGELOG como notas del
  release cuando existe, y el paquete incluye el CHANGELOG y la guía de
  migración.

#### Corregido

- Las migraciones de `--auth` y `--demo` funcionan también en MySQL.

### Fase 6: capa para IA

#### Agregado

- Recetas en `docs/ai/recipes/` del proyecto: crear un CRUD, proteger rutas,
  endpoint JSON, crear un componente (modal con `<dialog>`), migraciones,
  subir archivos y enviar correos, cada una con tests y errores comunes.
- `docs/ai/reference.md` con la API pública generada desde los PHPDoc
  (`bin/bootzen-reference`; un test avisa si queda vieja) y `llms.txt` en
  el proyecto y en el repositorio.
- Skills `crear-recurso`, `crear-componente` y `revisar-seguridad` en
  `.claude/skills/` y `.agents/skills/`.
- `bootzen-new mi-app --demo`: auth más una app de tareas por usuario con
  modal de borrado, JSON paginado, filtros, seeder y tests.
- Subida de archivos: `$request->file('campo')` con `store()` (privado) y
  `storePublic()` (en `public/uploads`), nombre aleatorio, extensión según el
  tipo detectado y bloqueo de extensiones ejecutables. `TestClient::file()` y
  `TestClient::image()` para los tests.
- `make:controller --json` (sin vista).

#### Cambiado

- `asset()` devuelve rutas relativas al dominio (`/assets/app.css?v=...`), con
  el subdirectorio de `APP_URL` si lo hay.
- El login y el registro del scaffold redirigen a `config('auth.home')`.
- `make:component` usa la prop `title`, como los componentes incluidos.
- El CSS del skeleton se genera desde un proyecto `--demo`.

### Fase 5: CLI y generadores

#### Agregado

- `make:resource Modelo campo:tipo[:nullable] ... [--table=]`: migración,
  modelo, controlador con validación, ruta `resource`, vistas index, create,
  edit y show con componentes, y tests de feature que pasan `composer check`
  sin tocar nada. Tipos: `string`, `text`, `email`, `int`, `float`, `bool`,
  `date` y `datetime`. Plurales en inglés; `--table` nombra tabla, rutas y
  vistas. Genera SQL de SQLite o MySQL según la conexión.
- `make:controller`, `make:model` (`--migration`, `--table`),
  `make:migration` (con `CREATE TABLE` si se llama `crear_x`),
  `make:middleware`, `make:seeder`, `make:test` (`--unit`) y
  `make:component` (`--class`), desde plantillas en `framework/stubs`.
  Ningún generador sobrescribe archivos.
- `route:list`, `config:cache`, `config:clear`, `key:generate`, `doctor`
  (PHP, extensiones, permisos, `.env`, `APP_DEBUG` en producción, base de
  datos, migraciones pendientes y CSS) y `check` (formato, PHPStan y tests).
- `BootZen\Testing\TestClient` y `TestResponse` con `client()` en los
  proyectos: peticiones con cookies de sesión, `actingAs`, `from`,
  `followingRedirects` y aserciones de estado, HTML, JSON, cabeceras, sesión,
  errores de validación y base de datos.
- Componentes `<x-textarea>` y `<x-checkbox>`.

#### Cambiado

- `back()` y la redirección por error de validación vuelven a la página del
  `Referer` si es del mismo sitio.
- En `APP_ENV=testing` se ignora la caché de configuración.
- Los tests del skeleton y del scaffold `--auth` usan `client()`.

### Fase 4: sesión, seguridad y autenticación

#### Agregado

- Sesión propia en archivos (`storage/sessions`) con `StartSession`: flash,
  `old()`, errores de validación, token CSRF y cookie `HttpOnly`,
  `SameSite=Lax` y `Secure` en HTTPS. Si la petición no usa la sesión no se
  crea archivo ni cookie. Driver `array` para tests.
- `VerifyCsrfToken`: token en `_token` o en la cabecera `X-CSRF-TOKEN`,
  respuesta 419 con su vista. Rutas excluidas con `session.csrf_except`.
- `Validator` con reglas `required`, `nullable`, `string`, `integer`,
  `numeric`, `boolean`, `array`, `email`, `url`, `min`, `max`, `between`,
  `in`, `confirmed`, `date`, `regex`, `unique`, `exists`, `file`, `image` y
  `mimes`, comodines (`items.*.precio`), closures y mensajes en español.
  `Request::validate()` vuelve al formulario con errores e input, o responde
  422 en JSON.
- `Response::with()`, `withErrors()` y `withInput()`; helpers `session()`,
  `back()`, `auth()` y `mailer()`.
- `Auth` con guard de sesión (`attempt`, `login`, `logout`, `user`,
  `intended`), `Hash` (Argon2id o bcrypt) y `PasswordReset`.
- Middleware `auth`, `guest`, `throttle:max,minutos` (`RateLimiter` sobre la
  caché) y `SecurityHeaders` (nosniff, frame options, referrer policy, HSTS en
  HTTPS y CSP configurable). Middleware con parámetros
  (`ParameterizedMiddleware`).
- `Mailer` con drivers `log` y `array`.
- `bootzen-new --auth`: registro, login, logout y recuperación de contraseña
  con vistas, migración, límite de intentos y tests.
- Skeleton: grupos `web` y `api`, `routes/api.php`, `config/session.php`,
  `auth.php`, `mail.php`, `cache.php` y `security.php`, vista 419, mensajes
  flash en el layout y `<x-input>` con `old()`.

#### Cambiado

- El campo CSRF se llama `_token` y los helpers de sesión ya no usan
  `session_start()`.
- `Request::getClientIp()` ignora las cabeceras de proxy cuando el cliente se
  conecta directo con una IP pública, para que no se pueda falsear la IP.
- `Cache` acepta un directorio `null` para guardar en memoria.

### Fase 3: datos

#### Agregado

- `BootZen\Database\Connection` sobre PDO: SQLite por defecto (WAL, claves
  foráneas, `busy_timeout`) y MySQL. Consultas con parámetros enlazados y
  `QueryException` con el SQL y los valores. Transacciones anidables.
- `QueryBuilder`: `select`, `where` (y `orWhere`, `whereIn`, `whereNull`,
  `whereRaw`), `join`, `orderBy`, `limit`, `paginate`, `insert`, `update` y
  `delete`. Identificadores validados y entre comillas; `update()` y
  `delete()` exigen un `where`.
- `Paginator` serializable a JSON y componente `<x-pagination>`.
- `Model` (Active Record ligero): `find`, `findOrFail` (404 automático),
  `create`, `save`, `update`, `delete`, `$fillable`, `$hidden`, `$casts`
  (`int`, `float`, `bool`, `string`, `array`, `datetime`, `date`), timestamps,
  `hasMany` y `belongsTo`.
- Migraciones con SQL directo (`Migrator`, por lotes y con transacción en
  SQLite) y seeders (`Seeder`, `SeederRunner`).
- Comandos `migrate`, `migrate:rollback`, `migrate:status`, `db:seed`,
  `db:fresh` (pide `--force` en producción) y `db:backup` (`VACUUM INTO`).
- Helper `db()`. Devolver un modelo o paginador desde un controlador responde
  JSON.
- Skeleton: `config/database.php`, variables `DB_*`, `database/migrations`,
  `database/seeds/DatabaseSeeder.php`, `app/Models` y tests con SQLite en
  memoria y migraciones aplicadas en `createApp()`.
- CI: tests de integración contra MySQL 8.4.

### Fase 2: vistas, componentes y frontend

#### Agregado

- Motor de vistas (`BootZen\View`): vistas `.php` con una lista cerrada de
  directivas (`{{ }}`, `{!! !!}`, `{{-- --}}`, `@{{ }}`, `@if`, `@foreach`
  con `@empty`, `@csrf`, `@method`, `@error`, `@props`) y componentes
  `<x-*>` con slots con nombre, `$attributes` (`merge`, `class`) y clases
  opcionales en `app/View/Components`. Compila a `storage/cache/views`,
  conserva la numeración de líneas y los errores apuntan a la vista original.
- Helpers `view()` y `class_list()`; `ExceptionHandler` renderiza
  `errors/{codigo}` con el motor y la página de depuración muestra la línea de
  la vista original.
- Skeleton: componentes `<x-layouts.app>`, `<x-navigation>`, `<x-card>`,
  `<x-button>`, `<x-alert>`, `<x-form>` y `<x-input>`; bienvenida y páginas de
  error con el layout.
- Tailwind CSS v4 con el CLI standalone: `bin/bootzen` lo descarga en
  `storage/bin/` y verifica su SHA-256 contra el release (v4.3.3). CSS
  precompilado en `skeleton/public/assets/app.css`.
- `bin/bootzen` en el skeleton: `serve` (con Tailwind en modo watch),
  `css:install`, `css:build`, `css:watch`, `view:cache`, `view:clear`.
- Runtime `public/assets/js/bootzen.js`: componentes `data-component` con
  limpieza al quitarlos del DOM, `request()` con JSON y CSRF, `toast()`,
  `data-confirm`, `data-remote`, `data-dismiss` y botones deshabilitados al
  enviar. Componentes de ejemplo `counter` y `menu`.
- Tests del runtime JS con `node --test` y jsdom; CI compila el CSS con el
  binario real y falla si el CSS del skeleton quedó desactualizado.

#### Eliminado

- `BootZen\Core\Component` y los componentes como clases PHP
  (`framework/src/Components`): ahora son vistas `<x-*>` del skeleton.

### Fase 1: núcleo HTTP

#### Agregado

- `Env`: carga `.env` sin dependencias (comillas, comentarios, `export`,
  interpolación `${VAR}`), sin sobrescribir variables reales del entorno.
- `Config`: archivos de `config/` con notación de puntos (`config('app.name')`)
  y caché en un solo archivo (`Config::writeCache()`).
- `Logger` de archivo diario con niveles PSR-3 y `Cache` de archivos con
  `remember()`, `increment()` y expiración.
- Router: patrones compilados una vez, restricciones `{id:\d+}` y `where()`,
  rutas con nombre y `route()`, grupos con `as`, `resource()`, respuestas
  405 con `Allow`, `OPTIONS` y `HEAD` automáticos.
- `Container::call()`: inyecta parámetros de ruta por nombre (con conversión
  a `int`, `float` y `bool`) y dependencias por tipo.
- `Pipeline` único para middleware global y de ruta, con alias y grupos.
- `Request`: `input()`, `all()`, `only()`, `except()`, `has()`, `filled()`,
  `boolean()`, `integer()`, `param()`, `wantsJson()` y `_method` para
  formularios.
- `Response::noContent()` y `withHeaders()`.
- `ExceptionHandler`: vistas `errors/{codigo}.php`, JSON automático, página
  de depuración con `APP_DEBUG` y registro de errores 5xx en el log.
- Helpers `app()`, `config()`, `route()` y `cache()`; `env()` convierte
  `true`, `false`, `null` y `empty`.
- Skeleton: `.env.example`, `config/app.php`, páginas de error, rutas con
  nombre, alias `cors` y grupo `api`. `bootzen-new` genera `.env` con una
  `APP_KEY` aleatoria.
- CI: job de cobertura con `pcov`.

#### Cambiado

- `Application` recibe la raíz del proyecto (`new Application(dirname(__DIR__))`)
  y carga `.env` y `config/` al construirse. `boot()` se elimina.
- El router lanza `HttpException` 404/405 en vez de devolver respuestas.
- `Response::json()` no escapa barras ni caracteres Unicode y acepta listas.

#### Corregido

- `Request::fromGlobals()` no incluía `Content-Type` ni `Content-Length` en
  las cabeceras (en `$_SERVER` llegan sin el prefijo `HTTP_`), así que
  `isJson()` siempre era falso en peticiones reales. Un cuerpo JSON que no es
  objeto ya no rompe el tipo del cuerpo.
- Un prefijo de grupo generaba paths con doble barra y `//ruta` se
  interpretaba como host.
- El router respondía todo `OPTIONS` con cabeceras CORS abiertas aunque no se
  registrara el middleware.

#### Eliminado

- Handlers `'Clase@metodo'`: usa `[Clase::class, 'metodo']`.
- `Application::send()`: `handle()` envía la respuesta y `run()` la devuelve.
- `Application::__call()` (proxy mágico al router) y `Router::middleware()`
  para la "siguiente ruta": usa `$app->routes()` y `->middleware()` en la ruta.
- Respuesta automática de `CorsMiddleware` a todo `OPTIONS`: ahora CORS se
  aplica solo donde se registra el middleware.

### Fase 0: limpieza y reorganización

#### Agregado

- `framework/bootstrap.php` con autoloader propio para `BootZen\` y `App\`:
  los proyectos funcionan sin `vendor/` en producción.
- `skeleton/`: el boilerplate como archivos reales (rutas, controlador,
  vista, tests, `AGENTS.md` para agentes de IA, configuración de Pest, PHPStan
  y PHP CS Fixer).
- `bin/bootzen-new`: instalador de proyectos en PHP, multiplataforma.
- `HttpException`: `abort()` la lanza y `Application` responde con su código
  de estado en HTML o JSON.
- Helper `base_path()`, ruta `/up` de health check en el skeleton.
- `composer.json` de desarrollo en la raíz, tests del framework con Pest y
  workflow `ci.yml` (PHP 8.2, 8.3 y 8.4 más verificación del proyecto
  generado).

#### Cambiado

- `core/src` se mueve a `framework/src`.
- `init_project.sh` pasa a ser un envoltorio de `bin/bootzen-new`.
- `redirect()`, `json_response()` y `download()` retornan un `Response` en vez
  de terminar la ejecución. `abort()` lanza `HttpException`.
- El release publica un tarball del framework y el skeleton en lugar de
  `bootzen.phar`, después de pasar `composer check`.
- `install.sh` solo exige PHP y git; Composer es opcional.

#### Eliminado

- Distribución en `.phar` (`build-phar.php`, `core/stub.php`).
- `Cache` y `CacheTenant` (Redis/Predis) y la caché de `Component`.
- `HomeController` y `ApiRoutes` de ejemplo dentro del framework.
- Polyfills de `rand`, `mt_rand`, `random_int` y `random_bytes`.
- Scripts `bin/make.sh`, `start.sh`, `analyzer.sh` y `count_lines.sh`
  (los reemplaza la CLI `bin/bootzen` en la fase 5).
- Dependencias de runtime del proyecto generado: `vlucas/phpdotenv`,
  `predis/predis`, `monolog/monolog` y `symfony/var-dumper`.

---

## [1.1.0] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.1.0.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.
- Se optimiza el proceso de generación del archivo `.phar`.
- Se actualiza el script de instalación para manejar mejor las configuraciones
  de shell.

---

## [1.0.9] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.9.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.
- Se elimina la firma del archivo `.phar` para evitar problemas de integridad al
  copiar/renombrar.

---

## [1.0.8] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.8.

---

## [1.0.7] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.7.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.5] - 2025-10-18

### Cambios

- Se actualiza la versión del framework a 1.0.5.
- Se mejora la documentación en varios archivos.
- Se corrigen errores menores en el código.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.4] - 2025-10-18

### Cambios

- BootZen ahora distribuye el framework como archivo `.phar` versionado
  (`bootzen-<version>.phar`).
- El workflow de GitHub Actions compila y publica automáticamente el `.phar` en
  cada release.
- El instalador descarga el último `.phar` desde GitHub Releases y lo guarda en
  `~/.bootzen/bootzen.phar`.
- Al crear un nuevo proyecto con `init_project.sh`, el `.phar` se copia
  automáticamente a `public/` del proyecto.
- El archivo `public/index.php` está preparado para cargar y usar el framework
  desde el `.phar` si existe.
- Se actualiza la documentación para reflejar el nuevo flujo de instalación y
  uso del `.phar`.

### Notas

> Esta versión marca el inicio del sistema de distribución y actualización
> automática del framework mediante archivos `.phar` versionados. Facilita la
> actualización y el uso de BootZen en nuevos proyectos.

---

## [1.0.2] - 2025-09-24

### Cambios

- Se agrega advertencia visible en README.md sobre el estado no estable del
  proyecto.
- Se crea y documenta el archivo CHANGELOG.md.
- Se mejora la documentación para clarificar el uso recomendado y el estado de
  los releases.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.1] - 2025-09-24

### Cambios

- Mejoras menores en scripts de instalación y actualización.
- Se agrega el script `updater.sh` para actualizar BootZen automáticamente.
- Documentación ampliada en README.md.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## [1.0.0] - 2025-09-23

### Cambios

- Inicialización del proyecto BootZen.
- Estructura base del microframework PHP.
- Script de instalación automática (`install.sh`).
- Script principal para crear proyectos (`init_project.sh`).
- Documentación inicial.

### Notas

> Esta versión aún no es un release estable. No se recomienda su uso en
> producción hasta que se publique un release oficial.

---

## Estado actual

**BootZen aún no cuenta con un release estable.**

- Los tags publicados son versiones preliminares para pruebas y desarrollo.
- Se recomienda esperar al primer release oficial antes de usar en producción.
- Para reportar errores o sugerencias, visita
  [GitHub Issues](https://github.com/lgzarturo/bootzen/issues).
