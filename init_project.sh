#!/usr/bin/env bash
#
# init_project.sh: envoltorio de compatibilidad.
#
# La creación de proyectos vive en bin/bootzen-new (PHP, multiplataforma).
# Este script se mantiene para quien ya lo tiene en el PATH.
#
# Uso: init_project.sh [nombre] [--no-git]
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

exec php "$SCRIPT_DIR/bin/bootzen-new" "$@"
