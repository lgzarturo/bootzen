#!/bin/sh
# Servicio "scheduler" de docker-compose.yml: hace de cron dentro del contenedor y ejecuta
# php bin/bootzen schedule:run al inicio de cada minuto, como el usuario de Apache.
set -e

cd /var/www/html

while true; do
    runuser -u www-data -- php bin/bootzen schedule:run || true
    sleep $((60 - $(date +%S | sed 's/^0//')))
done
