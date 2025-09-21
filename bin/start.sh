#!/bin/bash
#
# start.sh
#
# Autor: Arturo Lopez (https://github.com/lgzarturo)
#
# Descripción:
#   Este script inicia el entorno de desarrollo para un proyecto PHP en Arch Linux.
#   Verifica que las dependencias necesarias estén instaladas, instala las dependencias
#   del proyecto, configura la base de datos y compila los assets.
#   Finalmente, abre varias terminales para ejecutar el servidor PHP y otros servicios.
#

set -e

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     🚀 PHP Development Environment     ║${NC}"
echo -e "${GREEN}║              BootZen Setup             ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
echo ""

# Verificar dependencias
echo -e "\n${YELLOW}📋 Verificando dependencias...${NC}"

check_command() {
    if ! command -v $1 &> /dev/null; then
        echo -e "${RED}❌ $1 no está instalado${NC}"
        return 1
    else
        echo -e "${GREEN}✅ $1 instalado${NC}"
        return 0
    fi
}

all_installed=true
check_command php || all_installed=false
check_command composer || all_installed=false
check_command npm || all_installed=false
check_command mysql || all_installed=false
check_command redis-cli || all_installed=false

if [ "$all_installed" = false ]; then
    echo -e "${RED}Por favor instala las dependencias faltantes${NC}"
    exit 1
fi

# Crear proyecto
if [ ! -f "composer.json" ]; then
    echo -e "\n${YELLOW}📦 Instalando dependencias de Composer...${NC}"
    composer install
fi

if [ ! -d "node_modules" ]; then
    echo -e "\n${YELLOW}📦 Instalando dependencias de NPM...${NC}"
    npm install
fi

# TODO: Validar la inicialización de la base de datos
# Configurar base de datos
#echo -e "${YELLOW}🗄️ Configurando base de datos...${NC}"
#php scripts/init-db.php

# Compilar assets
echo -e "\n${YELLOW}🎨 Compilando assets...${NC}"
npm run build

# Iniciar servicios
echo -e "\n${GREEN}✨ Iniciando servicios...${NC}"

# Terminal 1: PHP Server
gnome-terminal --tab --title="PHP Server" -- bash -c "php -S localhost:8000 -t public; exec bash"

# Terminal 2: Tailwind Watch
gnome-terminal --tab --title="Tailwind" -- bash -c "npm run tailwindcss:watch; exec bash"

# TODO: Soportar el uso de Redis o Docker con los contenedores.
# Terminal 3: Redis
# gnome-terminal --tab --title="Redis" -- bash -c "redis-server; exec bash"

echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║         ✅ Entorno Iniciado            ║${NC}"
echo -e "${GREEN}╠════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║  🌐 App: http://localhost:8000         ║${NC}"
echo -e "${GREEN}║  🔧 VSCode: code .                     ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Comandos útiles:${NC}"
echo "  make help         - Ver todos los comandos"
echo "  make test         - Ejecutar tests"
echo "  make analyze      - Analizar código"
echo "  make count        - Contar líneas"
echo "  composer check    - Verificación completa"