#!/bin/bash
#
# updater.sh - Actualizador automático de BootZen
#
# Autor: Arturo Lopez (https://github.com/lgzarturo)
#
# Descripción:
#   Este script actualiza BootZen en $HOME/.bootzen al último release, tag o main.
#
# Versión: 1.0.3
#

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # Sin color

REPO_URL="https://github.com/lgzarturo/bootzen"
INSTALL_DIR="$HOME/.bootzen"
GITHUB_API="https://api.github.com/repos/lgzarturo/bootzen"

# Verificar existencia de BootZen
if [ ! -d "$INSTALL_DIR/.git" ]; then
    echo -e "${RED}BootZen no está instalado en $INSTALL_DIR. Ejecuta primero el instalador.${NC}"
    exit 1
fi

cd "$INSTALL_DIR"

echo -e "${YELLOW}Buscando el último release disponible...${NC}"
LATEST_RELEASE=$(curl -fsSL "$GITHUB_API/releases/latest" | grep 'tag_name' | head -1 | sed 's/.*"tag_name": "\([^"]*\)".*/\1/')

if [ -n "$LATEST_RELEASE" ]; then
    echo -e "${GREEN}Actualizando a release: $LATEST_RELEASE${NC}"
    git fetch --tags
    git checkout "$LATEST_RELEASE"
    git pull origin "$LATEST_RELEASE"
    echo -e "${GREEN}BootZen actualizado al último release: $LATEST_RELEASE${NC}"
    exit 0
fi

echo -e "${YELLOW}No hay releases, buscando el último tag...${NC}"
LATEST_TAG=$(git tag | sort -V | tail -n1)
if [ -n "$LATEST_TAG" ]; then
    echo -e "${GREEN}Actualizando a tag: $LATEST_TAG${NC}"
    git fetch --tags
    git checkout "$LATEST_TAG"
    git pull origin "$LATEST_TAG" || true
    echo -e "${GREEN}BootZen actualizado al último tag: $LATEST_TAG${NC}"
    exit 0
fi

echo -e "${YELLOW}No hay tags, actualizando rama main...${NC}"
git checkout main
git pull origin main

echo -e "${GREEN}BootZen actualizado a la última versión de main.${NC}"
exit 0
