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

Antes de subir: `php bin/bootzen css:build`, `php bin/bootzen view:cache` y
`php bin/bootzen doctor` (revisa PHP, extensiones, permisos y `.env`). En el
servidor, `php bin/bootzen config:cache` deja la configuración en un archivo.
En el servidor, después de cada despliegue: `php bin/bootzen migrate`.
La aplicación no necesita `vendor/` en producción. Sube el proyecto y apunta
el document root del servidor a `public/` y sirve el sitio con HTTPS (las
cookies de sesión se marcan como seguras solas). Asegúrate de que `storage/` y
`database/` tengan permisos de escritura.

## Guía para IA

Las convenciones del proyecto están en [AGENTS.md](AGENTS.md).
