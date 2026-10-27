#!/usr/bin/env bash
# Simula un hosting compartido: Apache con la raíz web en la carpeta del proyecto (no en public/),
# .htaccess permitidos y sin terminal. Instala con /_bootzen/instalar y revisa que nada privado se vea.
# Uso, con el artefacto de un proyecto --demo: bash <repo>/tests/deploy/hosting-compartido.sh build/<app>-<versión>.zip
set -euo pipefail
source "$(dirname "$0")/lib.sh"

ZIP=$1
PORT=${PORT:-8092}
BASE="http://127.0.0.1:$PORT"
WORK=$(mktemp -d)
NAME=bootzen-hosting-$$
# Apache deja los archivos como www-data: se devuelven al usuario actual antes de borrarlos,
# y la salida del script es la de la prueba, no la de la limpieza
cleanup() {
    local status=$?
    docker exec "$NAME" chown -R "$(id -u):$(id -g)" /var/www/html > /dev/null 2>&1 || true
    docker rm -f "$NAME" > /dev/null 2>&1 || true
    rm -rf "$WORK" 2> /dev/null || echo "Aviso: no se pudo borrar $WORK" >&2
    exit "$status"
}
trap cleanup EXIT

unzip -q "$ZIP" -d "$WORK"
mv "$WORK"/*/ "$WORK/public_html"
KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
sed -e 's/^APP_ENV=.*/APP_ENV=production/' -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
    -e "s#^APP_KEY=.*#APP_KEY=$KEY#" -e 's/^INSTALL_ENABLED=.*/INSTALL_ENABLED=true/' \
    "$WORK/public_html/.env.example" > "$WORK/public_html/.env"
printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' > "$WORK/hosting.conf"

docker run -d --name "$NAME" -p "$PORT:80" -v "$WORK/public_html:/var/www/html" \
    -v "$WORK/hosting.conf:/etc/apache2/conf-enabled/hosting.conf:ro" php:8.3-apache \
    sh -c 'a2enmod rewrite > /dev/null && chown -R www-data:www-data /var/www/html && apache2-foreground' > /dev/null
wait_up "$BASE"

# Instalador: clave equivocada, clave correcta y bloqueo
test "$(curl -s -o /dev/null -w '%{http_code}' -d 'key=otra' "$BASE/_bootzen/instalar")" = 403
curl -fsS --data-urlencode "key=$KEY" "$BASE/_bootzen/instalar" | grep -q 'Instalación lista'
expect_status 404 "$BASE/_bootzen/instalar"

register_user "$BASE" ana@hosting.test

for path in /.env /.env.example /composer.json /BUILD /config/app.php /framework/bootstrap.php \
    /bin/bootzen /database/app.sqlite /storage/install.lock /public/../.env; do
    expect_status 404 "$BASE$path"
done
expect_status 200 "$BASE/assets/app.css"

echo "Hosting compartido: OK"
