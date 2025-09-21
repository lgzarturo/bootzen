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

# Ahora puedes crear proyectos base desde cualquier lugar:
init_project.sh MiNuevoProyecto
```

Esto te permitirá ejecutar `init_project.sh` desde cualquier directorio y crear proyectos BootZen rápidamente.

## Instalación automática

También puedes instalar BootZen automáticamente con el script `install.sh`, que realiza los pasos anteriores por ti:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/lgzarturo/bootzen/main/install.sh)"
```

Este script detecta si usas bash o zsh y añade la ruta a tu `$PATH` en el archivo de configuración correspondiente.

### Sobre el script de instalación

El script `install.sh` está diseñado para ser seguro y fácil de usar. Clona el repositorio en `~/.bootzen`, añade la ruta a tu `$PATH`, y recarga la configuración del shell para que puedas usar los comandos de BootZen inmediatamente.
