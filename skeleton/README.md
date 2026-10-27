# __APP_NAME__

Aplicación construida con [BootZen](https://github.com/lgzarturo/bootzen).

## Requisitos

- PHP 8.2 o superior
- Composer (solo para desarrollo: tests, análisis estático y formato)

## Desarrollo

```bash
php bin/bootzen serve             # servidor + Tailwind en modo watch: http://127.0.0.1:8000
composer install                  # herramientas de desarrollo (tests, PHPStan, formato)
composer check                    # formato, PHPStan y tests
php bin/bootzen help              # todos los comandos
```

El CSS se compila con el binario standalone de Tailwind, que `bin/bootzen`
descarga la primera vez en `storage/bin/` (verificado con SHA-256). No hace
falta Node. Sin conexión puedes seguir usando `php -S localhost:8000 -t public`
con el CSS ya compilado.

## Despliegue

Antes de subir: `php bin/bootzen css:build` y `php bin/bootzen view:cache`.
La aplicación no necesita `vendor/` en producción. Sube el proyecto y apunta
el document root del servidor a `public/`. Asegúrate de que `storage/` y
`database/` tengan permisos de escritura.

## Guía para IA

Las convenciones del proyecto están en [AGENTS.md](AGENTS.md).
