# Desplegar la aplicación

Tres caminos, del más simple al más flexible. Los tres están probados con la app
de demostración (`bootzen-new mi-app --demo`):

| Dónde                                          | Guía                                         | Necesitas terminal en el servidor |
| ---------------------------------------------- | -------------------------------------------- | --------------------------------- |
| Hosting compartido (cPanel, Plesk, etc.)       | [hosting-compartido.md](hosting-compartido.md) | No                                |
| VPS con Nginx o Caddy y PHP-FPM                | [vps.md](vps.md)                             | Sí (SSH)                          |
| Docker (en un VPS, en tu máquina o en un PaaS) | [docker.md](docker.md)                       | Docker y Docker Compose           |

## El artefacto: `deploy:build`

```bash
php bin/bootzen deploy:build --version=1.0.0
```

Compila el CSS y arma `build/<app>-1.0.0/` más `build/<app>-1.0.0.zip` con solo
lo que el servidor necesita: `app/`, `bootstrap/`, `bin/bootzen`, `config/`,
`database/migrations` y `seeds`, `framework/`, `public/`, `resources/views/`,
`routes/` y la estructura de `storage/`. Las vistas van precompiladas.

Nunca lleva `.env`, la base SQLite, `tests/`, `vendor/`, logs, sesiones ni
archivos subidos, así que puedes descomprimirlo encima de una versión anterior
sin pisar datos. Sin `--version` usa la fecha (`20260115093000`). Con
`--no-css` empaqueta el `public/assets/app.css` que ya tienes.

## Lista para cualquier servidor

- `.env` con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con tu dominio y
  una `APP_KEY` propia (`php bin/bootzen key:generate` la crea en tu `.env`
  local; cópiala o genera otra con
  `php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"`).
- PHP 8.2 o superior con `pdo_sqlite` (o `pdo_mysql`), `mbstring` y `fileinfo`.
- El servidor web apunta a `public/`. Si no puedes elegirlo, el `.htaccess` de
  la raíz manda todo a `public/` y bloquea el resto.
- `storage/` y la carpeta de la base SQLite con permiso de escritura para PHP.
- `php bin/bootzen doctor` revisa todo lo anterior y te dice qué falta.
- `GET /up` responde `{"status":"ok"}`: úsalo para monitorear.

## Respaldos

Con SQLite toda la información está en un archivo. `php bin/bootzen db:backup`
crea una copia consistente (aunque haya peticiones escribiendo) en
`storage/backups/backup-AAAAMMDD-HHMMSS.sqlite`. Prográmalo una vez al día y
guarda las copias fuera del servidor. Cada guía tiene el comando concreto.

## Hasta dónde llega SQLite

SQLite con WAL aguanta muchas lecturas a la vez y una escritura a la vez (las
demás esperan hasta 5 segundos, `busy_timeout`). Para un MVP con cientos de
usuarios activos sobra. Cambia a MySQL cuando:

- Tengas muchas escrituras simultáneas y empiecen a aparecer errores de
  `database is locked` en `storage/logs`.
- Necesites más de un servidor de aplicación.
- La base viva en un disco de red (NFS, algunos volúmenes de PaaS): SQLite
  no es confiable ahí.

El cambio es de `.env` (`DB_CONNECTION=mysql`, `DB_HOST`, etc.). Las migraciones
son SQL directo: las que generó `make:resource` con SQLite hay que revisarlas
para MySQL (tipos `VARCHAR`, `AUTO_INCREMENT`). Las de `--auth` y `--demo` ya
traen las dos versiones.
