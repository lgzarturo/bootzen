#!/usr/bin/env bash
# Funciones comunes de las pruebas de despliegue (CI): esperar a que responda y registrar un usuario.

wait_up() {
    for _ in $(seq 1 60); do
        curl -fsS "$1/up" 2>/dev/null | grep -q '"ok"' && return 0
        sleep 1
    done
    echo "No respondió $1/up" >&2
    return 1
}

expect_status() {
    local status
    status=$(curl -s -o /dev/null --path-as-is -w '%{http_code}' "$2")
    if [ "$status" != "$1" ]; then
        echo "Esperaba $1 en $2 y llegó $status" >&2
        return 1
    fi
}

# Registra un usuario con CSRF y sesión reales, y comprueba que puede ver sus tareas
register_user() {
    local base=$1 email=$2 jar token
    jar=$(mktemp)
    token=$(curl -fsS -c "$jar" -b "$jar" "$base/registro" | grep -o 'name="_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
    test -n "$token"
    curl -fsS -o /dev/null -c "$jar" -b "$jar" --data-urlencode "_token=$token" \
        -d "nombre=Ana&email=$email&password=secreto-largo&password_confirmation=secreto-largo" "$base/registro"
    curl -fsS -c "$jar" -b "$jar" -H 'Accept: application/json' "$base/tareas" | grep -q '"total":0'
    rm -f "$jar"
}

# Inicia sesión con un usuario que ya existe y comprueba que llega a sus tareas
login_user() {
    local base=$1 email=$2 jar token
    jar=$(mktemp)
    token=$(curl -fsS -c "$jar" -b "$jar" "$base/login" | grep -o 'name="_token" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//')
    test -n "$token"
    curl -fsS -o /dev/null -c "$jar" -b "$jar" --data-urlencode "_token=$token" \
        -d "email=$email&password=secreto-largo" "$base/login"
    curl -fsS -c "$jar" -b "$jar" -H 'Accept: application/json' "$base/tareas" | grep -q '"total":'
    rm -f "$jar"
}
