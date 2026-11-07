# __APP_NAME__

Aplicación construida con [BootZen](https://github.com/lgzarturo/bootzen).

## Requisitos

- PHP 8.2 o superior
- Composer (solo para desarrollo: tests, análisis estático y formato)

## Desarrollo

```bash
php bin/bootzen migrate           # crea database/app.sqlite y aplica las migraciones
php bin/bootzen serve             # servidor + Tailwind en modo watch: http://127.0.0.1:8000
composer install                  # herramientas de desarrollo (tests, PHPStan, formato)
composer check                    # formato, PHPStan y tests
php bin/bootzen make:resource Tarea titulo:string hecha:bool   # CRUD con tests
php bin/bootzen help              # todos los comandos
```

Los formularios ya traen sesión, CSRF y validación. Si creaste el proyecto
con `--auth`, también tienes registro, login y recuperación de contraseña: el
enlace de recuperación aparece en `storage/logs` mientras `MAIL_DRIVER=log`.

El CSS se compila con el binario standalone de Tailwind, que `bin/bootzen`
descarga la primera vez en `storage/bin/` (verificado con SHA-256). No hace
falta Node. Sin conexión puedes seguir usando `php -S localhost:8000 -t public`
con el CSS ya compilado.

## Despliegue

```bash
php bin/bootzen deploy:build --version=1.0.0   # build/<app>-1.0.0.zip, listo para subir
```

Guías probadas paso a paso en [docs/deploy/](docs/deploy/README.md):

- [Hosting compartido](docs/deploy/hosting-compartido.md): subes el `.zip` y
  migras desde el navegador, sin terminal.
- [VPS](docs/deploy/vps.md): `deploy/deploy.sh` con Nginx o Caddy, sin cortes y
  con vuelta atrás.
- [Docker](docs/deploy/docker.md): `docker compose up -d --build`.

En cualquier servidor, `php bin/bootzen doctor` te dice qué falta. La aplicación
no necesita `vendor/` en producción.

## Actualizar BootZen

```bash
php bin/bootzen framework:update   # copia framework/ desde ~/.bootzen y deja respaldo
```

Antes, actualiza tu instalación local de BootZen con `updater.sh`.

## Guía para IA

Las convenciones del proyecto están en [AGENTS.md](AGENTS.md).
