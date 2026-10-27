# BootZen: Manual de Instalación

## Requisitos

- PHP 8.2 o superior (con `mbstring`, `fileinfo` y `pdo_sqlite`)
- git
- Composer (opcional, solo para desarrollo: tests, PHPStan y formato)

## Instalación automática

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/install.sh)"
```

El script verifica los requisitos, clona BootZen en `~/.bootzen` y agrega
`~/.bootzen/bin` a tu `PATH` (en `.bashrc` o `.zshrc`). Después recarga tu
shell:

```bash
source ~/.bashrc   # o source ~/.zshrc
```

## Instalación manual

```bash
git clone https://github.com/lgzarturo/bootzen ~/.bootzen
echo 'export PATH="$HOME/.bootzen/bin:$HOME/.bootzen:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

En Windows no necesitas tocar el `PATH`: ejecuta el instalador con PHP.

```powershell
git clone https://github.com/lgzarturo/bootzen $HOME\.bootzen
php $HOME\.bootzen\bin\bootzen-new mi-app
```

## Crear un proyecto

```bash
bootzen-new mi-app          # o init_project.sh mi-app (compatibilidad)
cd mi-app
php -S localhost:8000 -t public
```

`bootzen-new` copia el boilerplate (`skeleton/`) y el código del framework
(`framework/`) dentro del proyecto. No hay `.phar` ni dependencias de Composer
en producción: el proyecto funciona tal cual en cualquier servidor con PHP.

Opciones:

- `--auth`: agrega registro, login, logout y recuperación de contraseña.
- `--demo`: `--auth` más una app de tareas completa para tomar de ejemplo.
- `--no-git`: no inicializa un repositorio git.

## Actualizar BootZen

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/updater.sh)"
```

Mueve `~/.bootzen` al último release (o al último tag, o a `main`). Los
proyectos nuevos usarán esa versión. Para llevarla a un proyecto existente:

```bash
cd mi-app
php bin/bootzen framework:update
```

Reemplaza `framework/` con el de `~/.bootzen` (o el de `BOOTZEN_HOME`, o
`--from=ruta`) y deja un respaldo en `storage/backups/`. No baja de versión ni
pisa cambios que hayas hecho a mano en `framework/` salvo que uses `--force`.
Después revisa el [CHANGELOG](CHANGELOG.md) y corre `composer check`.

Si vienes de la v1 (la del `.phar`), sigue
[docs/MIGRACION-V1-V2.md](docs/MIGRACION-V1-V2.md).
