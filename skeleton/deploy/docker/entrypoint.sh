#!/bin/sh
# Arranque del contenedor: revisa la APP_KEY, guarda la caché de configuración, migra y
# deja storage/ escribible para Apache. Después ejecuta el comando de la imagen (Apache).
set -e

cd /var/www/html

if [ -z "$APP_KEY" ] && ! grep -qs '^APP_KEY=.\+' .env; then
    echo "Falta APP_KEY. Genérala con: php bin/bootzen key:generate (y pásala al contenedor)" >&2
    exit 1
fi

# Los volúmenes nuevos llegan vacíos: recrea la estructura de storage/
mkdir -p storage/app storage/backups storage/cache/views storage/cache/data storage/database storage/logs storage/sessions

# Primero la caché de configuración: la del volumen puede ser de un arranque con otras variables
php bin/bootzen config:cache > /dev/null

if [ "${MIGRATE_ON_START:-true}" = "true" ]; then
    php bin/bootzen migrate
fi

chown -R www-data:www-data storage public/uploads

exec "$@"
