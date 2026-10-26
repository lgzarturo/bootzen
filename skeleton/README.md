# __APP_NAME__

Aplicación construida con [BootZen](https://github.com/lgzarturo/bootzen).

## Requisitos

- PHP 8.2 o superior
- Composer (solo para desarrollo: tests, análisis estático y formato)

## Desarrollo

```bash
composer install                  # herramientas de desarrollo
php -S localhost:8000 -t public   # abre http://localhost:8000
composer check                    # formato, PHPStan y tests
```

## Despliegue

La aplicación no necesita `vendor/` en producción. Sube el proyecto y apunta
el document root del servidor a `public/`. Asegúrate de que `storage/` y
`database/` tengan permisos de escritura.

## Guía para IA

Las convenciones del proyecto están en [AGENTS.md](AGENTS.md).
