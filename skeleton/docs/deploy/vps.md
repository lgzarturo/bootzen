# VPS con Nginx o Caddy

Para un servidor Debian o Ubuntu donde tienes SSH y `sudo`. Cada despliegue sube
una versión nueva a su propia carpeta y cambia un enlace al final, así que no
hay cortes y volver atrás es un comando.

```
/var/www/tienda/
├── current -> releases/1.2.0     # lo que sirve Nginx o Caddy
├── releases/
│   ├── 1.1.0/
│   └── 1.2.0/
└── shared/                       # lo que sobrevive a cada versión
    ├── .env
    ├── database/app.sqlite
    ├── storage/{app,logs,sessions,backups}
    └── uploads/                  # public/uploads
```

Los archivos de ejemplo están en `deploy/` del proyecto. En todos, cambia
`tienda`, `tienda.example.com` y `deploy` por tus nombres.

## Preparar el servidor (una sola vez)

Como root (o con `sudo`):

```bash
apt install php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-mysql rsync
apt install nginx certbot python3-certbot-nginx     # o: apt install caddy

# Usuario que despliega y con el que corre PHP (así no hay líos de permisos con SQLite)
adduser --disabled-password --gecos "" deploy
mkdir -p /var/www/tienda/shared && chown -R deploy:deploy /var/www/tienda
```

Agrega tu llave pública a `/home/deploy/.ssh/authorized_keys` y prueba
`ssh deploy@tu-servidor`.

### PHP-FPM

`deploy/php-fpm.conf` crea un pool que corre como `deploy`:

```bash
cp deploy/php-fpm.conf /etc/php/8.3/fpm/pool.d/tienda.conf
systemctl reload php8.3-fpm
```

Para que `deploy.sh` pueda recargarlo al terminar (opcional pero recomendado),
crea `/etc/sudoers.d/deploy` con:

```
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
```

### Nginx

```bash
cp deploy/nginx.conf /etc/nginx/sites-available/tienda
ln -s /etc/nginx/sites-available/tienda /etc/nginx/sites-enabled/tienda
nginx -t && systemctl reload nginx
certbot --nginx -d tienda.example.com
```

Solo se ejecuta `index.php`: cualquier otro `.php` (por ejemplo uno subido a
`uploads/`) responde 404, igual que los archivos ocultos.

### O Caddy (HTTPS automático)

En `tienda.conf` del pool cambia `listen.owner` y `listen.group` a `caddy`, y:

```bash
cp deploy/Caddyfile /etc/caddy/Caddyfile
systemctl reload php8.3-fpm caddy
```

### El `.env` compartido

Desde tu máquina:

```bash
scp .env.example deploy@tu-servidor:/var/www/tienda/shared/.env
```

En el servidor, edita `/var/www/tienda/shared/.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tienda.example.com
APP_KEY=base64:...                                       # una clave propia
DB_DATABASE=/var/www/tienda/shared/database/app.sqlite   # ruta absoluta, fuera de releases/
```

y protégelo con `chmod 600 /var/www/tienda/shared/.env`.

## Desplegar

Desde la carpeta del proyecto, en tu máquina (necesitas `rsync` y `ssh`):

```bash
deploy/deploy.sh deploy@tu-servidor /var/www/tienda 1.2.0
```

La versión es opcional (sin ella usa la fecha). El script:

1. Arma el artefacto con `deploy:build` y lo sube a `releases/1.2.0` con rsync.
2. Enlaza `.env`, `storage/{app,logs,sessions,backups}` y `public/uploads`
   desde `shared/`.
3. Si usas SQLite y la base ya existe, la respalda con `db:backup`.
4. Ejecuta `migrate` y `config:cache`.
5. Cambia `current` a la versión nueva y recarga PHP-FPM.
6. Borra versiones viejas (deja 5; cámbialo con `KEEP_RELEASES=10`) y muestra
   `doctor`.

Si falta el `.env`, la `APP_KEY` o la ruta absoluta de SQLite, se detiene antes
de tocar nada. Las sesiones viven en `shared/`, así que nadie pierde su login
al desplegar.

Si tu servicio de PHP-FPM se llama distinto: `PHP_FPM_SERVICE=php8.2-fpm
deploy/deploy.sh ...`.

## Volver atrás

```bash
ssh deploy@tu-servidor
ln -sfn /var/www/tienda/releases/1.1.0 /var/www/tienda/current
sudo systemctl reload php8.3-fpm
```

Las migraciones no se revierten solas. Si la versión nueva cambió tablas y la
anterior no las entiende, ejecuta `php bin/bootzen migrate:rollback` desde
`current` **antes** de cambiar el enlace, o restaura el respaldo que dejó el
despliegue en `shared/storage/backups/`.

## Respaldos

Con `crontab -e` como `deploy`:

```
15 3 * * * cd /var/www/tienda/current && php bin/bootzen db:backup > /dev/null && find /var/www/tienda/shared/storage/backups -name '*.sqlite' -mtime +14 -delete
```

Copia `shared/storage/backups/` a otro lugar (otro servidor, almacenamiento de
objetos) con la herramienta que prefieras.

## Revisar

```bash
cd /var/www/tienda/current && php bin/bootzen doctor
tail -f /var/www/tienda/shared/storage/logs/app-$(date +%F).log
curl https://tienda.example.com/up
```
