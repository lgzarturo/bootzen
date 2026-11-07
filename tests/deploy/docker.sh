#!/usr/bin/env bash
# Construye la imagen del proyecto con docker compose, registra un usuario, reinicia y comprueba que los
# datos sigan ahí. Uso, desde la carpeta de un proyecto --demo: bash <repo>/tests/deploy/docker.sh
set -euo pipefail
source "$(dirname "$0")/lib.sh"

export APP_PORT=${APP_PORT:-8091}
export COMPOSE_PROJECT_NAME=bootzen-ci-$$
BASE="http://127.0.0.1:$APP_PORT"
trap 'docker compose down -v > /dev/null 2>&1 || true' EXIT

docker compose up -d --build --quiet-pull
wait_up "$BASE"
register_user "$BASE" ana@docker.test
expect_status 404 "$BASE/.env"

docker compose down
docker compose up -d
wait_up "$BASE"
docker compose exec -T -u www-data app php bin/bootzen doctor
docker compose exec -T -u www-data app php bin/bootzen db:backup

# El usuario sobrevivió al reinicio
login_user "$BASE" ana@docker.test

echo "Docker: OK"
