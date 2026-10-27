#!/usr/bin/env bash
# Publica una versión nueva en un VPS sin cortar el servicio:
#   1. arma el artefacto con bin/bootzen deploy:build
#   2. lo sube a <ruta>/releases/<versión> con rsync
#   3. enlaza lo compartido (.env, base, sesiones, logs, archivos) desde <ruta>/shared
#   4. respalda la base SQLite, migra y guarda la caché de configuración
#   5. cambia el enlace <ruta>/current y recarga PHP-FPM
#
# Uso: deploy/deploy.sh usuario@servidor /var/www/tienda [versión]
# Variables opcionales: PHP_FPM_SERVICE (php8.3-fpm), KEEP_RELEASES (5)
# Guía completa (preparar el servidor la primera vez): docs/deploy/vps.md

set -euo pipefail

if [ $# -lt 2 ]; then
    echo "Uso: deploy/deploy.sh usuario@servidor /var/www/tienda [versión]" >&2
    exit 1
fi

SERVER=$1
APP_DIR=${2%/}
VERSION=${3:-$(date +%Y%m%d%H%M%S)}

php bin/bootzen deploy:build --version="$VERSION"
BUILD=$(find build -maxdepth 1 -type d -name "*-$VERSION" | head -n 1)

if [ -z "$BUILD" ]; then
    echo "No se encontró el artefacto de la versión $VERSION en build/." >&2
    exit 1
fi

RELEASE="$APP_DIR/releases/$VERSION"
ssh "$SERVER" "mkdir -p '$RELEASE'"
rsync -az --delete "$BUILD/" "$SERVER:$RELEASE/"

ssh "$SERVER" bash -s -- "$APP_DIR" "$RELEASE" "${PHP_FPM_SERVICE:-php8.3-fpm}" "${KEEP_RELEASES:-5}" <<'REMOTE'
set -euo pipefail
APP_DIR=$1 RELEASE=$2 FPM=$3 KEEP=$4
SHARED="$APP_DIR/shared"

if [ ! -f "$SHARED/.env" ]; then
    echo "Falta $SHARED/.env. Créalo desde .env.example (guía: docs/deploy/vps.md)." >&2
    rm -rf "$RELEASE"
    exit 1
fi

if ! grep -qs '^APP_KEY=.' "$SHARED/.env"; then
    echo "Falta APP_KEY en $SHARED/.env. Genérala en tu máquina con php bin/bootzen key:generate y cópiala." >&2
    rm -rf "$RELEASE"
    exit 1
fi

if grep -qs '^DB_CONNECTION=sqlite' "$SHARED/.env" && ! grep -qs '^DB_DATABASE=/' "$SHARED/.env"; then
    echo "Con SQLite, DB_DATABASE debe ser una ruta absoluta fuera de releases/ (por ejemplo $SHARED/database/app.sqlite)." >&2
    rm -rf "$RELEASE"
    exit 1
fi

mkdir -p "$SHARED/storage/app" "$SHARED/storage/logs" "$SHARED/storage/sessions" "$SHARED/storage/backups" "$SHARED/uploads" "$SHARED/database"

ln -sfn "$SHARED/.env" "$RELEASE/.env"
for dir in app logs sessions backups; do
    rm -rf "${RELEASE:?}/storage/$dir"
    ln -s "$SHARED/storage/$dir" "$RELEASE/storage/$dir"
done
rm -rf "${RELEASE:?}/public/uploads"
ln -s "$SHARED/uploads" "$RELEASE/public/uploads"

cd "$RELEASE"
if grep -qs '^DB_CONNECTION=sqlite' .env && [ -n "$(find "$SHARED/database" -name '*.sqlite' -print -quit)" ]; then
    php bin/bootzen db:backup
fi
php bin/bootzen migrate
php bin/bootzen config:cache > /dev/null

ln -sfn "$RELEASE" "$APP_DIR/current"
sudo -n systemctl reload "$FPM" 2>/dev/null || echo "Aviso: no se pudo recargar $FPM (recárgalo a mano para limpiar OPcache)."

# Conserva las últimas versiones para poder volver atrás
ls -1dt "$APP_DIR"/releases/*/ | tail -n +"$((KEEP + 1))" | xargs -r rm -rf

php bin/bootzen doctor || true
echo "Publicada $(basename "$RELEASE")."
REMOTE
