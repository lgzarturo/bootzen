# Auditoría de seguridad, rendimiento y documentación (v2.9.0)

Fecha: 2026-11-12. Alcance: `framework/src`, `skeleton/`, `stubs/auth`,
`stubs/demo`, archivos de despliegue, instalador y documentación.

## Cómo se hizo

- Lectura del código que toca seguridad: Request, Response, sesión, CSRF,
  cabeceras, límites de peticiones, Auth, tokens de "recordarme",
  recuperación de contraseña, enlaces firmados, query builder, vistas,
  archivos subidos, discos, correo, cola, instalador web y manejo de errores.
- Pruebas reales sobre un proyecto creado con `bootzen-new demoapp --demo`,
  servido con `php -S` y 8 workers. Lo marcado como **comprobado** se
  reprodujo ahí; lo marcado como **lectura** sale solo del código.
- No se pudo correr `composer check`: `composer install` falló en este
  entorno al autenticarse contra GitHub. El estado de PHPStan y de los tests
  queda sin verificar en esta auditoría (CI lo cubre).

## Resumen

La base es buena. No encontré inyección SQL, XSS por defecto, fijación de
sesión, envenenamiento por cabecera Host ni deserialización de datos del
usuario. Lo que hay que arreglar se concentra en tres zonas: los límites de
intentos (se pueden saltar), el manejo de proxies y lo que el servidor web
hace con caché y compresión.

| #   | Hallazgo                                                        | Gravedad | Estado     |
| --- | --------------------------------------------------------------- | -------- | ---------- |
| S1  | `X-Forwarded-For` falso salta todos los límites tras un proxy   | Alta     | Comprobado |
| S2  | El contador de intentos no es atómico                           | Media    | Comprobado |
| S3  | Registro sin límite y sin freno al envío de correos             | Media    | Lectura    |
| S4  | Páginas privadas sin `Cache-Control`                            | Media    | Comprobado |
| S5  | Permisos 644 en `.env`, sesiones y base SQLite                  | Media    | Comprobado |
| S6  | `throttle` comparte contador entre rutas                        | Baja     | Lectura    |
| S7  | `Cache` deserializa objetos sin restricción                     | Baja     | Lectura    |
| S8  | CSRF apagado si `APP_ENV=testing` llega a producción            | Baja     | Lectura    |
| S9  | CORS sin `Vary: Origin`                                         | Baja     | Lectura    |
| S10 | FTP sin TLS y SFTP sin huella por defecto, sin aviso en doctor  | Baja     | Lectura    |
| S11 | Saltos de línea sin limpiar en mensajes del log                 | Baja     | Lectura    |
| R1  | `bootzen.js` y componentes se quedan viejos hasta 30 días       | Alta     | Comprobado |
| R2  | Apache (hosting y Docker) sin caché de assets; Nginx sin gzip   | Media    | Comprobado |
| R3  | Sesión creada y escrita en cada visita anónima                  | Media    | Comprobado |
| R4  | Descargas y subidas completas en memoria                        | Media    | Lectura    |
| R5  | `cursor()` pagina con OFFSET                                    | Baja     | Lectura    |
| D1  | Falta `SECURITY.md` y una guía de seguridad para producción     | Media    | Comprobado |
| D2  | Proxies de confianza sin documentar                             | Media    | Comprobado |

## Lo que está bien resuelto

- SQL: todo va con parámetros; columnas, operadores y dirección de orden
  pasan por lista blanca (`QueryBuilder::column()`, `operator()`).
  `update()` y `delete()` sin `where` se niegan.
- Vistas: `{{ }}` escapa con `ENT_QUOTES`; los atributos de componente se
  escapan en la bolsa. `bootzen.js` no usa `innerHTML`.
- Sesión propia con id de 160 bits, regenerado al entrar y al salir, cookie
  `HttpOnly` y `SameSite=Lax`, y `unserialize` con `allowed_classes: false`.
- Contraseñas con Argon2id, rehash automático y verificación contra un hash
  falso para no delatar por tiempo si el usuario existe.
- "Recordarme" con selector y validador hasheado, rotación en cada uso y
  borrado de todos los tokens si aparece una copia robada.
- Tokens de recuperación de 256 bits guardados como hash y con vencimiento.
- URLs absolutas desde `APP_URL`, nunca desde la cabecera `Host`.
- Asignación masiva cerrada por `$fillable`; `$hidden` fuera del JSON.
- Archivos: nombres al azar, extensión según el tipo detectado, extensiones
  ejecutables bloqueadas en discos públicos, descargas con
  `Content-Security-Policy: sandbox` y `nosniff`.
- Correo: direcciones validadas y cabeceras sin saltos de línea.
- Cola: el payload es JSON y solo instancia subclases de `Job`.
- CSV del panel con protección contra fórmulas.
- CSP estricta por defecto, `X-Frame-Options`, `nosniff`, `Referrer-Policy`,
  `Permissions-Policy` y HSTS en HTTPS.
- Página de error con traza solo con `APP_DEBUG=true`, y `doctor` lo marca
  como error en producción.
- Nginx y Caddy solo ejecutan `index.php`; el `.htaccess` raíz manda todo a
  `public/`, así `.env`, `storage/` y `database/` no quedan expuestos.
- Tailwind se descarga verificado con SHA-256.

## Seguridad

### S1. `X-Forwarded-For` falso salta los límites tras un proxy (alta)

`Request::getClientIp()` (`framework/src/Core/Request.php:635`) confía en
las cabeceras de proxy cuando `REMOTE_ADDR` es una IP privada o de loopback,
y toma el **primer** valor de `X-Forwarded-For`. Ese primer valor lo escribe
el cliente: Nginx con `$proxy_add_x_forwarded_for` y los balanceadores de
nube agregan la IP real al final, no reemplazan lo que llegó.

La guía `docs/deploy/docker.md` recomienda justo ese escenario (un proxy
delante del contenedor), así que es el caso normal en producción con Docker.

Comprobado: con la misma IP, el sexto intento de login queda bloqueado.
Cambiando `X-Forwarded-For` en cada petición pasaron 12 de 12 intentos. Lo
mismo aplica a `throttle:*`, al instalador web y a la recuperación de
contraseña.

El caso contrario también falla: detrás de Cloudflare con Nginx y PHP-FPM
directo, `REMOTE_ADDR` es una IP pública de Cloudflare, las cabeceras se
ignoran y todos los visitantes comparten contador.

Mejora:

- `config/security.php` con `trusted_proxies` (lista de IP o CIDR, vacía por
  defecto). Sin proxies de confianza se usa `REMOTE_ADDR` y nada más.
- Con proxies de confianza, recorrer `X-Forwarded-For` de derecha a
  izquierda y devolver la primera IP que no sea un proxy conocido.
- `isSecure()` debe aceptar `X-Forwarded-Proto` solo de esos proxies.
- En Docker, dejar `TRUSTED_PROXIES` documentado en `docker-compose.yml`.

### S2. El contador de intentos no es atómico (media)

`RateLimiter::hit()` y `Cache::increment()` leen el archivo, suman y
escriben, sin candado (`framework/src/Core/Cache.php`, `increment`). Las
peticiones en paralelo leen el mismo valor.

Comprobado: 40 logins incorrectos en paralelo contra un límite de 5; se
verificaron 13 contraseñas. Con más workers el margen crece, y Argon2 alarga
la ventana porque cada intento tarda.

Mejora: `increment()` con `flock` exclusivo sobre el archivo del contador
(abrir, bloquear, leer, escribir, soltar), y que `RateLimit` y el login
cuenten el intento **antes** de verificar la contraseña, no después.

### S3. Registro sin límite y sin freno al envío de correos (media)

`stubs/auth/routes/auth.php`: `POST /registro` no lleva `throttle`. Cada
registro manda un correo de verificación a la dirección que escriba quien
sea. Sirve para crear cuentas en masa y para usar el servidor de correo
contra terceros. `POST /login` tampoco lleva `throttle` por IP: solo el
límite por correo e IP del controlador, que deja probar cinco contraseñas
por minuto contra cada cuenta, sin tope por día ni por cuenta.

Mejora:

- `throttle` en `POST /registro`, `POST /login` y
  `POST /restablecer-contrasena`.
- Un segundo contador de login solo por correo (por ejemplo 20 por hora),
  para frenar ataques repartidos entre muchas IP.
- Bloqueo progresivo: que la espera crezca con los fallos seguidos.

### S4. Páginas privadas sin `Cache-Control` (media)

Comprobado: `/tareas` con sesión iniciada responde sin `Cache-Control`. Un
proxy o CDN mal configurado puede guardar la página de un usuario y servirla
a otro, y el botón atrás la muestra después de cerrar sesión. Las respuestas
llevan además `Set-Cookie` y el token CSRF en el HTML.

Mejora: `StartSession` agrega `Cache-Control: private, no-store` cuando la
respuesta no trae uno propio y la sesión se guardó. Una ruta pública que
quiera caché lo declara de forma explícita.

### S5. Permisos de archivos demasiado abiertos (media)

Comprobado en el proyecto generado: `.env`, `database/app.sqlite` y los
archivos de `storage/sessions/` quedan en 644, y las carpetas en 755. En un
hosting compartido sin aislamiento, otro usuario del servidor puede leer la
`APP_KEY`, la base completa y los ids de sesión (el id va en el nombre del
archivo, así que listar la carpeta basta para robar sesiones).

Mejora:

- `bootzen-new` y `key:generate` dejan `.env` en 600.
- `FileSessionStore`, `Cache`, `Logger` y la base SQLite crean sus archivos
  en 600 y sus carpetas en 700.
- Guardar la sesión bajo el hash del id (`sess_` + sha256), no bajo el id.
- `doctor` avisa si `.env` o la base son legibles por otros usuarios.

### S6. `throttle` comparte contador entre rutas (baja)

La clave es `throttle|max|minutos|ip` (`RateLimit::process`). Dos rutas con
`throttle:5,1` gastan el mismo contador: pedir cinco veces el correo de
recuperación bloquea también cerrar otras sesiones. Mejora: incluir el
nombre o el patrón de la ruta en la clave.

### S7. `Cache` deserializa objetos sin restricción (baja)

`Cache::unserializeQuietly()` llama a `unserialize()` sin `allowed_classes`.
Hoy solo el servidor escribe en `storage/cache/data`, así que hace falta
poder escribir archivos ahí para explotarlo; junto con S5 en un hosting
compartido deja de ser teórico. Mejora: firmar cada entrada con HMAC de
`APP_KEY` y descartar las que no validen, o guardar JSON cuando el valor sea
escalar o array (que es lo que usa el limitador).

### S8. CSRF apagado con `APP_ENV=testing` (baja)

`VerifyCsrfToken` se salta la verificación si `app.env` es `testing`. Un
`.env` copiado de pruebas a producción deja la app sin CSRF y nadie avisa.
Mejora: `doctor` lo marca como error, y el middleware solo lo permite en
CLI (`PHP_SAPI === 'cli'`).

### S9. CORS sin `Vary: Origin` (baja)

`CorsMiddleware` refleja el origen pero no agrega `Vary: Origin`; una caché
intermedia puede servir a un origen la respuesta autorizada para otro.
Además no hay forma documentada de pasar configuración al alias `cors` (el
contenedor lo construye con los valores por defecto, que permiten `*`).
Mejora: agregar la cabecera y leer `config/cors.php`.

### S10. Discos remotos sin cifrado ni verificación por defecto (baja)

`FtpDisk` usa FTP plano salvo que se ponga `ssl`, y `SftpDisk` sin
`fingerprint` acepta cualquier servidor. Está comentado en el código pero
`doctor` no lo avisa. Mejora: advertencia en `doctor` para ambos casos en
producción.

### S11. Saltos de línea en el log (baja)

`Logger::log()` escribe el mensaje tal cual. Un mensaje de excepción con
datos del usuario puede meter líneas falsas en el log. Mejora: cambiar
`\r` y `\n` por su forma escapada en el mensaje. De paso, los logs diarios
no se borran nunca: conviene una tarea que elimine los de más de N días.

### Notas menores

- `install.sh` y la instalación rápida bajan desde `main`, no desde el
  último release. Un commit roto o malicioso en `main` llega directo a
  quien instala. Mejor apuntar al último tag.
- El instalador web pide la `APP_KEY` en un formulario. Funciona, pero es la
  llave que firma todo. Un token de instalación aparte
  (`INSTALL_TOKEN`) evita teclearla en un navegador.
- `Response::send()` no quita `X-Powered-By`. `expose_php = Off` ya está en
  el `php.ini` de Docker; falta mencionarlo en las guías de VPS y hosting.
- Dos peticiones simultáneas con la misma cookie de "recordarme" pueden
  dejar al usuario sin cookie (la segunda no encuentra el selector y la
  borra). Es molestia, no agujero.

## Rendimiento

Medido en local sin OPcache: `/up` 1.5 ms, inicio 1.8 ms, lista de tareas
con sesión 7 ms (p50). Crear un proyecto tarda 0.06 s. El framework es
rápido; lo que falta está en la entrega al navegador.

### R1. JavaScript viejo hasta 30 días después de un despliegue (alta)

`asset()` agrega `?v=<fecha>` a `app.css` y `app.js`, y por eso
`deploy/nginx.conf` y el `Caddyfile` cachean `/assets/` 30 días. Pero
`app.js` importa `./bootzen.js` sin versión, y `bootzen.js` importa cada
componente con `new URL(name + '.js', componentsBase)`, también sin versión
(`skeleton/public/assets/js/bootzen.js:154`). Tras un despliegue, el
navegador recibe el `app.js` nuevo y sigue usando `bootzen.js` y los
componentes anteriores hasta por 30 días.

Mejora: un `importmap` generado por un helper (`@importmap` o
`asset_importmap()`) que asigne a cada módulo su URL con versión, o propagar
la versión de `import.meta.url` a los imports dinámicos. Con CSP hay que
permitir el importmap por hash o servirlo desde un archivo.

### R2. Caché y compresión según el servidor (media)

- Apache (hosting compartido y Docker): `public/.htaccess` no define
  `Cache-Control` ni `Expires` para `/assets/`, así que cada visita
  revalida CSS y JS. El Dockerfile activa `mod_headers` y no lo usa.
- Nginx: `deploy/nginx.conf` no activa `gzip` para CSS, JS ni JSON. La lista
  de tareas pesa 48 KB y comprimida queda en 3 KB; `app.css` baja de 25 KB a
  5.7 KB.
- Caddy ya comprime y cachea bien.

Mejora: bloque `mod_expires`/`mod_headers` y `mod_deflate` en
`public/.htaccess` (dentro de `IfModule`), y `gzip on` con `gzip_types` en
`nginx.conf`.

### R3. Sesión creada y escrita en cada visita anónima (media)

Comprobado: la página de inicio sin sesión responde con `Set-Cookie` y crea
un archivo en `storage/sessions`. El layout imprime el token CSRF en un
`<meta>`, eso crea el token y obliga a guardar la sesión. Además
`StartSession` guarda `_previous_url` y reescribe el archivo en cada GET.

Consecuencias: ninguna página pública se puede cachear, cada bot genera un
archivo, y la limpieza (2% de las peticiones) recorre toda la carpeta con
`glob`.

Mejora:

- Escribir la sesión solo si cambió (comparar un hash de los datos al
  cargar y al guardar) y renovar la fecha con `touch` en caso contrario.
- No guardar `_previous_url` en sesiones que no existen todavía.
- Evaluar que las páginas públicas sin formularios no creen token.

### R4. Archivos completos en memoria (media)

`Storage::download()` y `UploadedFile::contents()` cargan el archivo entero
en un string. Un PDF de 20 MB ocupa 20 MB por petición (más las copias), y
no hay soporte de `Range`. Mejora: `Disk::readStream()` y usar
`Response::stream()` en `download()`; en `LocalDisk::put` mover el archivo
subido en lugar de leerlo y escribirlo.

### R5. `cursor()` con OFFSET (baja)

`QueryBuilder::cursor()` pide cada bloque con `LIMIT/OFFSET`. En
exportaciones grandes el costo crece al cuadrado y, si entran filas durante
la exportación, puede repetir o saltar registros. Mejora: paginar por clave
(`WHERE id > ?`) cuando el orden es por `id`.

### Otras mejoras de velocidad

- `<link rel="modulepreload">` para `bootzen.js`: hoy hay una cadena de
  tres descargas (app.js, bootzen.js, componente).
- `route:cache` no existe y hoy no hace falta; conviene medirlo cuando un
  proyecto pase de unas 300 rutas.
- La guía de VPS debería recomendar `config:cache`, `view:cache` y OPcache
  con `validate_timestamps=0`, como ya hace la imagen de Docker.
- Helper para imágenes con `width`, `height` y `loading="lazy"`, que es lo
  que más pesa en CLS y LCP de un MVP típico.

## Documentación

Lo que hay es bueno y está probado: `skeleton/AGENTS.md` (846 líneas),
doce recetas, referencia generada del PHPDoc y tres guías de despliegue.
Un test revisa que los enlaces resuelvan y que la referencia esté al día.

Lo que falta:

- **D1. `SECURITY.md`** en la raíz: cómo reportar una vulnerabilidad,
  versiones con soporte y tiempos de respuesta. Hoy no hay canal.
- **D1. Guía de seguridad para producción** en `skeleton/docs/`: una lista
  corta con HTTPS obligatorio, `SESSION_SECURE_COOKIE=true`, permisos de
  `.env`, proxies de confianza, respaldos fuera del servidor, rotación de
  `APP_KEY` (y qué invalida), cómo ampliar la CSP y qué revisar con
  `doctor`. La sección "Seguridad que ya viene puesta" de `AGENTS.md` solo
  cubre CSRF, cabeceras y cookies.
- **D2. Proxies**: ninguna guía explica cómo sabe la app la IP real ni qué
  pasa con los límites detrás de Cloudflare o un balanceador.
- **Modelo de amenazas breve** en `docs/ARQUITECTURA.md`: qué protege el
  framework y qué queda del lado de la app (autorización por registro,
  validación, `{!! !!}`).
- **Límites de intentos**: documentar que el login limita por correo e IP y
  cómo ajustarlo.
- **Rendimiento**: una página con lo que hay que activar en producción
  (OPcache, cachés, compresión) y cómo medirlo.
- **CORS**: no hay receta ni archivo de configuración.
- **Actualización del framework**: `framework:update` está descrito, falta
  una nota de qué hacer cuando un release corrige un fallo de seguridad.

## Facilidad para crear proyectos

Funciona bien: un comando, 0.06 s, `.env` con `APP_KEY` generada, y
`doctor` en verde sin tocar nada. Lo que mejoraría:

- `bootzen-new` crea el `.env` con `APP_DEBUG=true` y `APP_ENV=local`, que
  es lo correcto en local; falta que `deploy:build` o `doctor` recuerden
  con más fuerza cambiarlo (hoy `doctor` solo falla si `APP_ENV` ya es
  `production`).
- `doctor --production` que revise como si fuera producción aunque el
  `.env` diga `local`: HTTPS en `APP_URL`, cookie segura, permisos (S5),
  `APP_ENV=testing` (S8), discos remotos (S10) y driver de correo `log`.
- Un test de humo en el skeleton que verifique las cabeceras de seguridad y
  el `Cache-Control` de una ruta privada, para que una app no las pierda
  sin enterarse.

## Plan propuesto

Fase A, seguridad (una versión menor, 2.10.0):

1. S1: proxies de confianza en `Request` y `config/security.php`.
2. S2: `Cache::increment()` con candado y conteo antes de verificar.
3. S3 y S6: `throttle` en registro, login y restablecer; clave por ruta;
   contador por cuenta.
4. S4: `Cache-Control: private, no-store` desde `StartSession`.
5. S5: permisos 600/700 y sesión bajo hash del id.
6. S8, S9, S10, S11: cambios chicos, cada uno con su test.

Fase B, entrega al navegador:

1. R1: versión en los imports de módulos.
2. R2: caché y compresión en `.htaccess` y `nginx.conf`, con verificación
   en `tests/deploy/*.sh`.
3. R3: escribir la sesión solo si cambió.

Fase C, archivos y datos: R4 (streams) y R5 (paginación por clave).

Fase D, documentación: `SECURITY.md`, guía de seguridad para producción,
sección de proxies, página de rendimiento y `doctor --production`.

Cada punto de las fases A a C va con su test en Pest, como pide la
convención del repo, y con la actualización de `skeleton/AGENTS.md` y de la
referencia cuando cambie una firma pública.
