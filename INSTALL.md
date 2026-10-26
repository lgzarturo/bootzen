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

- `--no-git`: no inicializa un repositorio git.

## Actualizar BootZen

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/updater.sh)"
```

Mueve `~/.bootzen` al último release (o al último tag, o a `main`). Los
proyectos nuevos usarán esa versión. Actualizar `framework/` dentro de un
proyecto existente llegará con el comando `framework:update` (ver
`plan/04_PLAN-MVP-MONOLITO.md`).
