# BootZen - Manual de Instalación

## Instalación manual

Puedes instalar los scripts de BootZen para usarlos desde cualquier terminal y crear proyectos base fácilmente:

```bash
# Clona el repositorio en tu carpeta de scripts personales
git clone https://github.com/lgzarturo/bootzen ~/.bootzen

# Añade la ruta a tu $PATH en tu archivo de configuración de shell
echo 'export PATH="$HOME/.bootzen:$PATH"' >> ~/.bashrc   # Para bash
echo 'export PATH="$HOME/.bootzen:$PATH"' >> ~/.zshrc    # Para zsh

# Recarga la configuración del shell
source ~/.bashrc   # o source ~/.zshrc

# Descarga manualmente el último archivo .phar desde GitHub Releases y guárdalo en ~/.bootzen/bootzen.phar
# (Opcional, el instalador automático lo hace por ti)

# Ahora puedes crear proyectos base desde cualquier lugar:
init_project.sh MiNuevoProyecto
```

Esto te permitirá ejecutar `init_project.sh` desde cualquier directorio y crear proyectos BootZen rápidamente. El archivo `bootzen.phar` se copiará automáticamente a la carpeta `public/` de cada nuevo proyecto y el `index.php` lo cargará si está presente.

## Instalación automática y uso del .phar

Puedes instalar BootZen automáticamente con el script `install.sh`, que realiza los pasos anteriores por ti y descarga el último archivo `.phar` publicado en GitHub Releases:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/install.sh)"
```

Este script detecta si usas bash o zsh, añade la ruta a tu `$PATH` en el archivo de configuración correspondiente y descarga el último `.phar` en `~/.bootzen/bootzen.phar`.

### ¿Cómo funciona el .phar en los proyectos?

Cada vez que creas un nuevo proyecto con `init_project.sh`, el archivo `bootzen.phar` se copia automáticamente a `public/` y el `index.php` lo carga si está presente. Así el framework se ejecuta directamente desde el archivo `.phar`, facilitando actualizaciones y distribución.

### Sobre el script de instalación

El script `install.sh` está diseñado para ser seguro y fácil de usar. Clona el repositorio en `~/.bootzen`, añade la ruta a tu `$PATH`, descarga el último `.phar` y recarga la configuración del shell para que puedas usar los comandos de BootZen inmediatamente.
