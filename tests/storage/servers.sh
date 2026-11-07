#!/usr/bin/env bash
# Levanta los servidores de prueba de los discos remotos e imprime las variables para los tests:
#   - S3 compatible (SeaweedFS, verifica la firma SigV4; el bucket "publico" se lee sin credenciales)
#   - FTP con TLS (vsftpd)
#   - SFTP (OpenSSH)
# Uso: eval "$(bash tests/storage/servers.sh)"   |   para bajarlos: bash tests/storage/servers.sh stop
set -euo pipefail

NAMES="bootzen-test-s3 bootzen-test-ftp bootzen-test-sftp"

if [ "${1:-}" = "stop" ]; then
    docker rm -f $NAMES > /dev/null 2>&1 || true
    exit 0
fi

WORK=$(mktemp -d)
cat > "$WORK/s3.json" <<'JSON'
{
  "identities": [
    {"name": "bootzen", "credentials": [{"accessKey": "bootzen", "secretKey": "secreto-s3-123"}], "actions": ["Admin", "Read", "Write", "List", "Tagging"]},
    {"name": "anonymous", "actions": ["Read:publico"]}
  ]
}
JSON
openssl req -x509 -newkey rsa:2048 -nodes -keyout "$WORK/key.pem" -out "$WORK/cert.pem" -days 2 -subj "/CN=127.0.0.1" > /dev/null 2>&1
chmod 644 "$WORK"/*

docker rm -f $NAMES > /dev/null 2>&1 || true

# Volúmenes chicos: SeaweedFS reserva espacio por volumen y llena el disco del runner si no se limita
docker run -d --name bootzen-test-s3 -p 8333:8333 -v "$WORK/s3.json:/etc/s3.json:ro" chrislusf/seaweedfs:3.97 \
    server -s3 -s3.config=/etc/s3.json -dir=/data -master.volumeSizeLimitMB=64 -volume.max=8 > /dev/null
docker run -d --name bootzen-test-ftp -p 2121:21 -p 21000-21010:21000-21010 \
    -e USERS="bootzen|clave-ftp-123|/home/bootzen|1000" -e ADDRESS=127.0.0.1 \
    -e TLS_CERT=/certs/cert.pem -e TLS_KEY=/certs/key.pem -v "$WORK:/certs:ro" delfer/alpine-ftp-server:latest > /dev/null
docker run -d --name bootzen-test-sftp -p 2223:22 atmoz/sftp:alpine "bootzen:clave-sftp-123:1001::archivos" > /dev/null

for _ in $(seq 1 60); do
    curl -s -o /dev/null http://127.0.0.1:8333/ && break
    sleep 1
done
sleep 3

cat <<'VARS'
export BOOTZEN_TEST_S3_ENDPOINT=http://127.0.0.1:8333 BOOTZEN_TEST_S3_KEY=bootzen BOOTZEN_TEST_S3_SECRET=secreto-s3-123
export BOOTZEN_TEST_FTP_HOST=127.0.0.1 BOOTZEN_TEST_FTP_PORT=2121 BOOTZEN_TEST_FTP_USERNAME=bootzen BOOTZEN_TEST_FTP_PASSWORD=clave-ftp-123
export BOOTZEN_TEST_SFTP_HOST=127.0.0.1 BOOTZEN_TEST_SFTP_PORT=2223 BOOTZEN_TEST_SFTP_USERNAME=bootzen BOOTZEN_TEST_SFTP_PASSWORD=clave-sftp-123 BOOTZEN_TEST_SFTP_ROOT=/archivos
VARS
