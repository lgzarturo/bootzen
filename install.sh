#!/bin/bash
#s
# install.sh - Instalador automático de BootZen
#
# Autor: Arturo Lopez (https://github.com/lgzarturo)
#
# Descripción:
#   Este script verifica los requisitos del sistema, clona el repositorio de BootZen,
#   y configura el entorno para que el usuario pueda usar BootZen desde cualquier terminal.
#
# Versión: 1.0.2
#

set -e

# Colores para la salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # Sin color

# Requisitos mínimos
REQUIRED_PHP="8.2"
REQUIRED_NODE="22"
REQUIRED_NPM="10"
REQUIRED_COMPOSER="2.8"
REQUIRED_NPX="10"

function version_ge() {
    # Compara dos versiones: $1 >= $2
    [ "$(printf '%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]
}

function check_version() {
    local cmd="$1"
    local required="$2"
    local actual="$3"
    local name="$4"
    if version_ge "$actual" "$required"; then
        echo -e "${GREEN}✔ $name ($actual) cumple con la versión mínima requerida ($required)${NC}"
        return 0
    else
        echo -e "${RED}✗ $name ($actual) NO cumple con la versión mínima requerida ($required)${NC}"
        return 1
    fi
}

# Validar comandos y versiones
echo -e "${YELLOW}Comprobando requisitos del sistema...${NC}"

# Validación acumulativa
FAILED=0

# PHP
if command -v php >/dev/null 2>&1; then
    PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    check_version "php" "$REQUIRED_PHP" "$PHP_VERSION" "PHP" || FAILED=1
else
    echo -e "${RED}PHP no está instalado. Instala PHP $REQUIRED_PHP o superior.${NC}"
    FAILED=1
fi

# Node
if command -v node >/dev/null 2>&1; then
    NODE_VERSION=$(node -v | sed 's/v//; s/\..*//')
    check_version "node" "$REQUIRED_NODE" "$NODE_VERSION" "Node.js" || FAILED=1
else
    echo -e "${RED}Node.js no está instalado. Instala Node.js $REQUIRED_NODE.x o superior.${NC}"
    FAILED=1
fi

# NPM
if command -v npm >/dev/null 2>&1; then
    NPM_VERSION=$(npm -v | cut -d. -f1)
    check_version "npm" "$REQUIRED_NPM" "$NPM_VERSION" "npm" || FAILED=1
else
    echo -e "${RED}npm no está instalado. Instala npm $REQUIRED_NPM.x o superior.${NC}"
    FAILED=1
fi

# Composer
if command -v composer >/dev/null 2>&1; then
    COMPOSER_VERSION=$(composer --version | grep -oE '[0-9]+\.[0-9]+')
    check_version "composer" "$REQUIRED_COMPOSER" "$COMPOSER_VERSION" "Composer" || FAILED=1
else
    echo -e "${RED}Composer no está instalado. Instala Composer $REQUIRED_COMPOSER.x o superior.${NC}"
    FAILED=1
fi

# NPX
if command -v npx >/dev/null 2>&1; then
    NPX_VERSION=$(npx -v | cut -d. -f1)
    check_version "npx" "$REQUIRED_NPX" "$NPX_VERSION" "npx" || FAILED=1
else
    echo -e "${RED}npx no está instalado. Instala npx $REQUIRED_NPX.x o superior.${NC}"
    FAILED=1
fi

if [ "$FAILED" -eq 1 ]; then
    echo -e "\n${RED}No se cumplen todos los requisitos mínimos. Se cancela la instalación.${NC}"
    exit 1
fi

REPO_URL="https://github.com/lgzarturo/bootzen"
INSTALL_DIR="$HOME/.bootzen"
SCRIPT_NAME="init_project.sh"

# Detectar shell
SHELL_NAME="$(basename "$SHELL")"
PROFILE=""
if [ "$SHELL_NAME" = "zsh" ]; then
    PROFILE="$HOME/.zshrc"
elif [ "$SHELL_NAME" = "bash" ]; then
    PROFILE="$HOME/.bashrc"
else
    echo -e "\n${YELLOW}Shell no detectado, usando .bashrc por defecto...${NC}"
    PROFILE="$HOME/.bashrc"
fi

# Clonar el repositorio
if [ -d "$INSTALL_DIR" ]; then
    echo -e "\n${YELLOW}BootZen ya está instalado en $INSTALL_DIR. Actualizando...${NC}"
    git -C "$INSTALL_DIR" pull
else
    echo -e "${GREEN}Clonando BootZen en $INSTALL_DIR...${NC}"
    git clone "$REPO_URL" "$INSTALL_DIR"
fi

# Agregar al PATH si no está
if ! grep -q "$INSTALL_DIR" "$PROFILE"; then
    echo -e "${GREEN}Agregando BootZen al PATH en $PROFILE...${NC}"
    echo "export PATH=\"$INSTALL_DIR:\$PATH\"" >> "$PROFILE"
else
    echo -e "\n${YELLOW}La ruta ya está en el PATH de $PROFILE.${NC}"
fi

# Recargar configuración del shell
echo -e "${YELLOW}IMPORTANTE:${NC} Para que los cambios surtan efecto en tu terminal actual, ejecuta manualmente:\n"
echo -e "    source $PROFILE\n"
echo -e "Esto actualizará tu entorno y permitirá usar BootZen desde cualquier terminal."

# Verificar disponibilidad del script principal
echo -e "${GREEN}Verificando la disponibilidad de '$SCRIPT_NAME'...${NC}"
if command -v $SCRIPT_NAME >/dev/null 2>&1; then
    echo -e "${GREEN}BootZen instalado correctamente. Puedes usar '$SCRIPT_NAME' desde cualquier terminal.${NC}"
    echo -e "\nEjemplo: $SCRIPT_NAME MiNuevoProyecto\n"
else
    echo -e "\n${RED}Error: '$SCRIPT_NAME' no está disponible en el PATH.${NC}"
    echo -e "\nAsegúrate de que la instalación se haya completado correctamente:\n"
    echo -e "   - Intenta ejecutar: source $PROFILE"
    echo -e "   - Luego intenta de nuevo: $SCRIPT_NAME MiNuevoProyecto"
    echo -e "   - Si el problema persiste, revisa el contenido de $PROFILE para asegurarte de que la ruta se agregó correctamente."
    echo -e "   - Verifica que $INSTALL_DIR exista y contenga los archivos de BootZen."
    echo -e "   - Valida que $INSTALL_DIR contenga el script '$SCRIPT_NAME'."
    echo -e "\nSi necesitas ayuda, visita: https://github.com/lgzarturo/bootzen/issues"
fi
echo -e "\n${GREEN}¡Gracias por instalar BootZen!${NC}"