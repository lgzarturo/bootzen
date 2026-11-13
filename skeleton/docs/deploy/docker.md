# Docker

El proyecto trae `Dockerfile`, `docker-compose.yml` y `.dockerignore`. La imagen
es `php:8.3-apache` con `public/` como raíz, OPcache y las vistas precompiladas.

## Levantar

Con una `APP_KEY` en tu `.env` (`bootzen-new` ya la crea; si no,
`php bin/bootzen key:generate`):

```bash
docker compose up -d --build
```

Abre `http://localhost:8080` (cambia el puerto con `APP_PORT=9000` en `.env`).

Al arrancar, el contenedor:

1. Se detiene con un mensaje claro si no hay `APP_KEY`.
2. Guarda la caché de configuración con las variables del contenedor.
3. Ejecuta las migraciones pendientes (`MIGRATE_ON_START=false` lo evita).
4. Deja `storage/` escribible para Apache.

Compose pasa al contenedor todas las variables de tu `.env` (las de correo
incluidas) y fuerza `APP_ENV=production`, `APP_DEBUG=false` y la ruta de la
base. El `.env` no entra a la imagen: se lee al arrancar.

Para enviar correos de verdad pon `MAIL_DRIVER=smtp` y los datos de tu
proveedor en `.env` (receta `docs/ai/recipes/enviar-correo.md`), reinicia
con `docker compose up -d` y prueba con
`docker compose exec -u www-data app php bin/bootzen mail:test tu@correo.com`.

## Tareas programadas y cola

`docker compose` levanta dos servicios con la misma imagen y los mismos
datos: `app` (la web) y `scheduler`, que ejecuta `schedule:run` al inicio de
cada minuto como `www-data` (hace de cron). Lo que pongas en
`routes/schedule.php` corre ahí; con `QUEUE_DRIVER=database`, programa
también `queue:work --stop-when-empty --max-time=50` cada minuto.

```bash
docker compose logs -f scheduler
docker compose exec -u www-data app php bin/bootzen schedule:list
```

## Dónde quedan los datos

| Volumen   | Ruta en el contenedor          | Qué guarda                                         |
| --------- | ------------------------------ | -------------------------------------------------- |
| `storage` | `/var/www/html/storage`        | Base SQLite (`storage/database/app.sqlite`), sesiones, logs, archivos privados y respaldos |
| `uploads` | `/var/www/html/public/uploads` | Archivos subidos con `storePublic()`               |

`docker compose down` y `up` los conservan; `docker compose down -v` los borra.

Si prefieres no guardar archivos en el servidor (por ejemplo, para tener
varias réplicas), usa un disco S3 o compatible: `STORAGE_DISK=s3` y
`STORAGE_PUBLIC_DISK=s3-public` en `.env` (receta `subir-archivos.md`). La
imagen ya trae `gd` (imágenes) y `ftp`.

## Actualizar

```bash
git pull                      # o los cambios que hayas hecho
docker compose up -d --build
```

Las migraciones nuevas corren al arrancar y las sesiones siguen vivas.

## Comandos dentro del contenedor

Usa `-u www-data` para que los archivos que se creen sean de Apache:

```bash
docker compose exec -u www-data app php bin/bootzen doctor
docker compose exec -u www-data app php bin/bootzen db:seed
docker compose logs -f app
```

## Respaldos

```bash
docker compose exec -u www-data app php bin/bootzen db:backup
docker compose cp app:/var/www/html/storage/backups ./respaldos
```

## HTTPS

El contenedor habla HTTP. Ponle delante el proxy que ya uses (Caddy, Nginx,
Traefik, el balanceador de tu proveedor) y define `APP_URL=https://...`. La app
reconoce HTTPS por la cabecera `X-Forwarded-Proto` y la IP real del visitante
por `X-Forwarded-For`, pero solo si vienen de un proxy de confianza. El
`docker-compose.yml` trae `TRUSTED_PROXIES=private`: confía en lo que llega
por redes privadas, que es por donde entra un proxy del mismo servidor o de la
red de Docker. Así las cookies salen con `Secure` y los límites de intentos
cuentan por visitante. Si el proxy llega desde una IP pública (un balanceador
externo), pon esa IP o su rango en `TRUSTED_PROXIES` del `.env`; si publicas el
puerto directo a internet sin proxy, déjalo vacío (`TRUSTED_PROXIES=`).

Con Caddy en el mismo servidor basta con:

```
tienda.example.com {
	reverse_proxy localhost:8080
}
```

## Con MySQL

Crea `docker-compose.mysql.yml` junto al otro:

```yaml
services:
  app:
    environment:
      DB_CONNECTION: mysql
      DB_HOST: db
      DB_DATABASE: tienda
      DB_USERNAME: tienda
      DB_PASSWORD: ${DB_PASSWORD:?Define DB_PASSWORD en .env}
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8.4
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: tienda
      MYSQL_USER: tienda
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_RANDOM_ROOT_PASSWORD: "yes"
    volumes:
      - mysql:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "--silent"]
      interval: 5s
      retries: 20

volumes:
  mysql:
```

Agrega `DB_PASSWORD=...` a tu `.env` y levanta los dos:

```bash
docker compose -f docker-compose.yml -f docker-compose.mysql.yml up -d --build
```

Para respaldar MySQL usa `mysqldump` desde el contenedor `db`; `db:backup` es
solo para SQLite.

## Sin Compose

```bash
docker build -t tienda .
docker run -d -p 8080:80 -e APP_KEY="base64:..." \
  -v tienda-storage:/var/www/html/storage -v tienda-uploads:/var/www/html/public/uploads tienda
```
