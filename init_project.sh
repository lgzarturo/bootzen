#!/bin/bash
#
# init_project.sh
#
# Autor: Arturo Lopez (https://github.com/lgzarturo)
#
# Descripción:
#   Script para inicializar un proyecto PHP monolítico con estructura moderna, configuración de herramientas de desarrollo,
#   dependencias y scripts auxiliares. Automatiza la creación de carpetas, archivos de configuración, instalación de Composer y NPM,
#   y prepara el entorno para comenzar a desarrollar.
#
# Uso:
#   ./init_project.sh [NOMBRE_DEL_PROYECTO]
#   Si no se especifica NOMBRE_DEL_PROYECTO, se usará "my-php-app" por defecto.
#
# Flujo de ejecución:
#   1. Definición de colores para salida en terminal.
#   2. Obtención del nombre del proyecto (argumento o valor por defecto).
#   3. Creación de la estructura de directorios principal y subdirectorios.
#   4. Copia de scripts auxiliares al nuevo proyecto y asignación de permisos de ejecución.
#   5. Cambio al directorio del proyecto recién creado.
#   6. Generación de archivos iniciales de configuración:
#      - composer.json (dependencias PHP y scripts)
#      - .env.example (variables de entorno)
#      - .gitignore (archivos y carpetas a ignorar por git)
#      - .php-cs-fixer.php (configuración de formato de código)
#      - phpstan.neon (configuración de análisis estático)
#      - pest.php (configuración de pruebas)
#      - phpunit.xml (configuración de PHPUnit)
#      - Archivos de configuración de VSCode (.vscode/settings.json, launch.json, extensions.json)
#      - resources/css/app.css (estilos base con Tailwind)
#      - public/index.php (página de inicio del proyecto)
#      - package.json (herramientas frontend y scripts)
#      - tailwind.config.js (configuración de Tailwind)
#   7. Inicialización de repositorio git y primer commit.
#   8. Instalación de dependencias de Composer y NPM.
#   9. Configuración de Husky para pre-commit con lint-staged.
#   10. Mensaje final con instrucciones para continuar el desarrollo.
#
# Requisitos:
#   - Bash o Zsh
#   - PHP 8.2 o superior
#   - Composer
#   - NPM
#   - Git
#   - OpenSSL
#
# Versión: 1.0.3
#

set -e

# Colores para la salida
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # Sin color

PROJECT_NAME=${1:-"my-php-app"}

echo -e "${GREEN}-Creando proyecto: $PROJECT_NAME${NC}"

# Crear la estructura de directorios
mkdir -p $PROJECT_NAME/{public/{css,js,images},src/{Controllers,Models,Views,Services,Database/{Migrations,Seeders},Core,Middleware,Helpers},config,tests/{Unit,Feature},views/{layouts,components,pages},storage/{logs,cache,uploads},.vscode,.scripts,resources/{css,js}}

# Copiar los scripts de utilidad
echo -e "\n${YELLOW}-Copiando scripts...${NC}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cp "$SCRIPT_DIR/bin/analyzer.sh" $PROJECT_NAME/.scripts/analyzer.sh
cp "$SCRIPT_DIR/bin/count_lines.sh" $PROJECT_NAME/.scripts/count_lines.sh
cp "$SCRIPT_DIR/bin/make.sh" $PROJECT_NAME/.scripts/make.sh
cp "$SCRIPT_DIR/bin/start.sh" $PROJECT_NAME/.scripts/start.sh
chmod +x $PROJECT_NAME/.scripts/analyzer.sh $PROJECT_NAME/.scripts/count_lines.sh $PROJECT_NAME/.scripts/make.sh $PROJECT_NAME/.scripts/start.sh

echo -e "${GREEN}-Scripts copiados.${NC}"

# Mover al directorio del proyecto
cd $PROJECT_NAME

# Crear archivos iniciales
echo -e "\n${YELLOW}-Creando archivos iniciales...${NC}"

# Crear composer.json
cat > composer.json <<EOL
{
    "name": "app/$PROJECT_NAME",
    "description": "BootZen - A new PHP monolithic project application",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "vlucas/phpdotenv": "^5.6.2",
        "predis/predis": "^3.2.0",
        "monolog/monolog": "^3.9.0",
        "symfony/var-dumper": "^7.3.3"
    },
    "require-dev": {
        "pestphp/pest": "^4.1.0",
        "friendsofphp/php-cs-fixer": "^3.87.2",
        "phpstan/phpstan": "^2.1.28",
        "fakerphp/faker": "^1.24.1",
        "mockery/mockery": "^1.6.12"
    },
    "autoload": {
        "psr-4": {
            "App\\\\": "src/"
        },
        "files": [
            "src/Helpers/helpers.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\\\": "tests/"
        }
    },
    "scripts": {
        "test": "vendor/bin/pest",
        "test:coverage": "vendor/bin/pest --coverage",
        "format": "vendor/bin/php-cs-fixer fix",
        "lint": "vendor/bin/phpstan analyse src tests",
        "check": [
            "@format",
            "@lint",
            "@test"
        ],
        "serve": "php -S localhost:8000 -t public",
        "make:controller": "php .scripts/make.php controller",
        "make:model": "php .scripts/make.php model",
        "make:middleware": "php .scripts/make.php middleware",
        "make:service": "php .scripts/make.php service",
        "make:migration": "php .scripts/make.php migration",
        "make:seeder": "php .scripts/make.php seeder",
        "make:test": "php .scripts/make.php test",
        "count:lines": "php .scripts/count_lines.php",
        "analyze": "php .scripts/analyze.php",
        "setup:env": "cp .env.example .env",
        "setup:app": "composer install"
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    }
}
EOL

# Crear .env.example
cat > .env.example <<EOL
APP_NAME=$PROJECT_NAME
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=base64:$(openssl rand -base64 32)

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=$PROJECT_NAME
DB_USERNAME=root
DB_PASSWORD=

CACHE_DRIVER=file
QUEUE_CONNECTION=sync

REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_PREFIX=app_${PROJECT_NAME}_

TENANT_MODE=single
DEFAULT_TENANT=main
EOL

# Crear .gitignore
cat > .gitignore <<EOL
/vendor/
/node_modules/
/.env
/.vscode/
/storage/logs/*
/storage/cache/*
/storage/uploads/*
!storage/*/index.html
!storage/*/.gitkeep
.phpunit.result.cache
.php-cs-fixer.cache
composer.lock
package-lock.json
yarn.lock
npm-debug.log
*.log
.DS_Store
Thumbs.db
.idea/
*.iml
*.swp
*.swo
EOL

# Crear archivo de configuración de PHP CS Fixer
cat > .php-cs-fixer.php <<EOL
<?php

\$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['vendor', 'storage'])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
    ])
    ->setFinder(\$finder);
EOL

# Crear archivo de configuración de PHPStan
cat > phpstan.neon <<EOL
includes:
    - vendor/phpstan/phpstan/conf/bleedingEdge.neon
parameters:
    level: 8
    paths:
        - src
        - tests
    excludePaths:
        - vendor/*
        - storage/*
        - .php-cs-fixer.php
    reportUnmatchedIgnoredErrors: false
EOL

# Crear archivo de configuración de Pest
cat > pest.php <<EOL
<?php

// Archivo de configuración de Pest. Puedes definir macros, hooks, etc. aquí.
EOL

# Crear archivo de configuración de PHPUnit
cat > phpunit.xml <<EOL
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         verbose="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </coverage>
</phpunit>
EOL

# Crear archivo de configuración de VSCode
cat > .vscode/settings.json <<EOL
{
    "php.validate.executablePath": "/usr/local/bin/php",
    "php.suggest.basic": false,
    "phpcs.enable": true,
    "phpcs.standard": "PSR12",
    "phpstan.enable": true,
    "phpstan.level": "max",
    "editor.formatOnSave": true,
    "editor.codeActionsOnSave": {
        "source.fixAll": true
    },
    "[php]": {
        "editor.defaultFormatter": "junstyle.php-cs-fixer",
        "editor.tabSize": 4
    },
    "php-cs-fixer.executablePath": "${workspaceFolder}/vendor/bin/php-cs-fixer",
    "php-cs-fixer.onsave": true,
    "php-cs-fixer.config": ".php-cs-fixer.php",
    "phpstan.enabled": true,
    "phpstan.configFile": "phpstan.neon",
    "phpstan.paths": ["${workspaceFolder}/src"],
    "files.associations": {
        "*.php": "php"
    },
    "files.exclude": {
        "**/vendor": true,  
        "**/node_modules": true,
        "**/.env": true,
        "**/storage/logs": true,
        "**/storage/cache": true,
        "**/storage/uploads": true,
        "**/.vscode": true
    }
}
EOL

cat > .vscode/launch.json <<EOL
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "${workspaceFolder}": "${workspaceFolder}"
            }
        }
    ]
}
EOL

cat > .vscode/extensions.json <<EOL
{
    "recommendations": [
        "junstyle.php-cs-fixer",
        "bmewburn.vscode-intelephense-client",
        "ikappas.phpcs",
        "felixfbecker.php-debug",
        "xdebug.php-debug",
        "neilbrayfield.php-docblocker",
        "mehedidracula.php-namespace-resolver",
        "onecentlin.laravel-blade",
        "amiralizadeh9480.laravel-extra-intellisense",
        "bradlc.vscode-tailwindcss",
        "cjhowe7.laravel-blade",
        "recca0120.vscode-phpunit",
        "open-southeners.phpstan-vscode",
        "ms-vscode.makefile-tools",
        "GitHub.vscode-github-actions"
    ]
}
EOL

# Estilos base para Tailwind CSS
cat > resources/css/app.css <<EOL
@tailwind base;
@tailwind components;
@tailwind utilities;
@plugin "@tailwindcss/typography";
@plugin "@tailwindcss/forms";

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f9f9f9;
    color: #333;
    line-height: 1.6;
    padding: 20px;
}

a {
    color: #3490dc;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

h1, h2, h3 {
    margin-bottom: 15px;
    color: #2c3e50;
}
EOL

# Crear archivo index.php en public
cat > public/index.php <<EOL
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BootZen Framework - $PROJECT_NAME</title>
    <link href="/css/app.css" rel="stylesheet">
    <link href="/css/index_welcome.css" rel="stylesheet">
    <script>
        // Aplica el tema oscuro si el usuario lo prefiere
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-gradient-to-br from-blue-100 via-white to-blue-200  dark:bg-gradient-to-br dark:from-gray-800 dark:via-gray-900 dark:to-black transition-colors min-h-screen flex items-center justify-center flex-col p-4">
    <button id="theme-toggle" class="fixed top-4 right-4 z-50 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100 px-4 py-2 rounded-lg shadow transition hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-400">
        <span id="theme-toggle-text">🌙 Modo oscuro</span>
    </button>
    <div class="max-w-xl w-full bg-white dark:bg-gray-900 rounded-2xl shadow-xl p-8 text-center border border-blue-200 dark:border-gray-700 animate-fade-in">
        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg" alt="PHP Logo" class="mx-auto mb-4 w-32 h-32 animate-bounce-slow">
        <h1 class="text-4xl font-bold text-blue-700 mb-2 animate-gradient-text bg-gradient-to-r from-blue-500 via-purple-400 to-pink-400 bg-clip-text text-transparent">¡Hello World!</h1>
        <h2 class="text-2xl font-semibold text-blue-500 animate-slide-down">Bienvenido a my-php-app</h2>
        <p class="text-lg text-gray-700 mb-6 animate-fade-in-delay">Tu aplicación PHP monolítica está lista y corriendo.</p>
        <div class="mb-6 animate-pop">
            <span class="inline-block bg-blue-100 text-blue-800 px-4 py-2 rounded-full font-mono text-sm shadow animate-pulse">http://localhost:8000/</span>
        </div>
        <a href="http://localhost:8000/" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg shadow transition transform hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-400 animate-pop">Ver aplicación</a>
        <div class="mt-8 text-sm text-gray-500 animate-fade-in-delay">
            <p class="prose dark:prose-invert">¿Listo para comenzar? Edita <code class="font-mono bg-gray-100 dark:bg-gray-800 rounded-lg px-2 py-1">src/Controllers</code> y <code class="font-mono bg-gray-100 dark:bg-gray-800 rounded-lg px-2 py-1">views/</code> para tu lógica y vistas.</p>
            <p class="prose dark:prose-invert lg:prose-xl mt-2 animate-slide-up">¡Feliz desarrollo! 🚀</p>
            <p class="mt-2 font-bold animate-fade-in-delay">
                <?php
                // Mostrar fecha en formato AM/PM y UTC-5
                \$timezone = getenv('APP_TIMEZONE') ?: 'America/Cancun';
                \$dt = new DateTime('now', new DateTimeZone(\$timezone));
                echo "Fecha actual: " . \$dt->format('Y-m-d h:i:s A') . " (" . \$dt->getTimezone()->getName() . ")";
                ?>
            </p>
        </div>
    </div>
    <footer class="mt-10 text-center text-sm text-gray-400 animate-fade-in-delay">
        <div class="flex flex-col items-center gap-2">
            <span>Este framework está construido con ❤️ desde <span class="font-bold text-blue-500">Cancún</span> (UTC-5) 🇲🇽🌴</span>
            <span>Desarrollado por <a href="https://github.com/lgzarturo" class="text-blue-500 hover:underline">Arturo Lopez</a></span>
        </div>
    </footer>
    <script>
        // Script para alternar el tema
        const themeToggle = document.getElementById('theme-toggle');
        const themeToggleText = document.getElementById('theme-toggle-text');
        function setTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
                themeToggleText.textContent = '☀️ Modo claro';
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
                themeToggleText.textContent = '🌙 Modo oscuro';
            }
        }
        themeToggle.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                setTheme('light');
            } else {
                setTheme('dark');
            }
        });
        // Inicializa el texto del botón
        if (document.documentElement.classList.contains('dark')) {
            themeToggleText.textContent = '☀️ Modo claro';
        } else {
            themeToggleText.textContent = '🌙 Modo oscuro';
        }
    </script>
    <style>
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fade-in 1s ease-out;
        }
        @keyframes fade-in-delay {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .animate-fade-in-delay {
            animation: fade-in-delay 1.5s 0.5s forwards;
            opacity: 0;
        }
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .animate-bounce-slow {
            animation: bounce-slow 2s infinite;
        }
        @keyframes gradient-text {
            to { background-position: 200% center; }
        }
        .animate-gradient-text {
            background-size: 200% 200%;
            animation: gradient-text 3s infinite alternate;
        }
        @keyframes slide-down {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-down {
            animation: slide-down 1s ease-out;
        }
        @keyframes slide-up {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .animate-slide-up {
            animation: slide-up 1s ease-out;
        }
        @keyframes pop {
            0% { transform: scale(0.95); }
            60% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .animate-pop {
            animation: pop 0.6s cubic-bezier(.17,.67,.83,.67);
        }
    </style>
</body>
</html>
EOL

# Crear archivo de estilos personalizado para la página de bienvenida
cat > public/css/index_welcome.css <<EOL
/* Estilos adicionales para la página de bienvenida */
.shadow-xl {
    box-shadow: 0 10px 32px 0 rgba(60, 120, 200, 0.12), 0 1.5px 4px 0 rgba(60, 120, 200, 0.08);
}
.rounded-2xl {
    border-radius: 1.25rem;
}
/* Modo oscuro para la página de bienvenida */
.dark body {
    background-color: #18181b;
    color: #e5e7eb;
}
.dark .bg-white {
    background-color: #23272f !important;
    color: #e5e7eb !important;
}
.dark .text-blue-700 {
    color: #60a5fa !important;
}
.dark .text-blue-500 {
    color: #3b82f6 !important;
}
.dark .border-blue-200 {
    border-color: #334155 !important;
}
.dark .shadow-xl {
    box-shadow: 0 10px 32px 0 rgba(30, 41, 59, 0.24), 0 1.5px 4px 0 rgba(30, 41, 59, 0.18);
}
.dark .bg-blue-100 {
    background-color: #334155 !important;
}
.dark .text-blue-800 {
    color: #60a5fa !important;
}
.dark .bg-blue-600 {
    background-color: #2563eb !important;
}
.dark .hover\:bg-blue-700:hover {
    background-color: #1d4ed8 !important;
}
.dark .text-gray-700 {
    color: #d1d5db !important;
}
.dark .text-gray-500 {
    color: #9ca3af !important;
}
.dark .text-gray-400 {
    color: #6b7280 !important;
}
/* Puedes agregar más estilos personalizados aquí si lo deseas */
EOL

# Crear package.json para herramientas de frontend
cat > package.json <<EOL
{
  "name": "$PROJECT_NAME-frontend",
  "version": "1.0.0",
  "description": "Frontend assets for $PROJECT_NAME",
  "scripts": {
    "dev": "concurrently \"npm run tailwindcss:watch\" \"npm run serve\"",
    "tailwindcss:watch": "tailwindcss -i ./resources/css/app.css -o ./public/css/app.css --watch",
    "build": "tailwindcss -i ./resources/css/app.css -o ./public/css/app.css --minify",
    "serve": "php -S localhost:8000 -t public",
    "prepare": "husky install"
  },
  "devDependencies": {
    "tailwindcss": "^3.3.6",
    "husky": "^8.0.3",
    "lint-staged": "^15.2.0",
    "concurrently": "^8.2.2",
    "@tailwindcss/forms": "^0.5.7",
    "@tailwindcss/typography": "^0.5.10"
  },
  "lint-staged": {
    "*.php": [
        "vendor/bin/php-cs-fixer fix",
        "vendor/bin/phpstan analyse",
        "vendor/bin/pest",
        "git add"
    ]
  }
}
EOL

cat > tailwind.config.js <<EOL
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    "./views/**/*.php",
    "./public/**/*.html",
    "./public/**/*.php",
    "./src/**/*.php"
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}
EOL

cat > src/Helpers/helpers.php <<EOL
<?php

// Puedes agregar funciones helper aquí.
EOL

cat > Makefile <<EOL
.PHONY: help install dev build start test format lint analyze count create-controller create-model create-service

help: ## Mostrar esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*\$\$' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", \$\$1, \$\$2}'

install: ## Instalar dependencias
	composer install
	npm install

dev: ## Iniciar servidor de desarrollo
	npm run dev

build: ## Compilar para producción
	composer install --no-dev --optimize-autoloader
	npm run build

start: ## Iniciar entorno de desarrollo
	bash .scripts/start.sh

test: ## Ejecutar tests
	vendor/bin/pest

format: ## Formatear código
	vendor/bin/php-cs-fixer fix

lint: ## Analizar código
	vendor/bin/phpstan analyse

analyze: ## Análisis completo del código
	php .scripts/analyzer.sh

count: ## Contar líneas de código
	php .scripts/count_lines.sh

create-controller: ## Crear nuevo controlador
	@read -p "Nombre del controlador: " name; php .scripts/make.sh controller \$\$name

create-model: ## Crear nuevo modelo
	@read -p "Nombre del modelo: " name; php .scripts/make.sh model \$\$name

create-service: ## Crear nuevo servicio
	@read -p "Nombre del servicio: " name; php .scripts/make.sh service \$\$name
EOL

echo -e "${GREEN}-Archivos de configuración iniciales creados.${NC}"

# Inicializar git y hacer el primer commit
echo -e "\n${YELLOW}-Inicializando repositorio git...${NC}"
git init
git add .
git commit -m "chore: Initial commit"
echo -e "${GREEN}-Repositorio git inicializado y primer commit realizado.${NC}"

# Instalar dependencias de Composer
echo -e "\n${YELLOW}-Instalando dependencias de Composer...${NC}"
composer install
npm install
echo -e "${GREEN}-Dependencias de Composer y NPM instaladas.${NC}"

# Configurar Husky para pre-commit con lint-staged
echo -e "\n${YELLOW}-Configurando Husky para pre-commit...${NC}"
npx husky install
npx husky add .husky/pre-commit "npx lint-staged"
echo -e "${GREEN}-Husky configurado.${NC}"

echo -e "\n${YELLOW}-Verificando instalaciones...${NC}"
php -v
npm -v
node -v
composer -v
git --version
echo -e "${GREEN}-Instalaciones verificadas.${NC}"

echo -e "\n${YELLOW}-Verificando paquetes instalados...${NC}"
npm list tailwindcss
npm list husky
npm list lint-staged
npm list concurrently
vendor/bin/phpstan --version
vendor/bin/pest --version
composer show friendsofphp/php-cs-fixer
echo -e "${GREEN}-Paquetes verificados.${NC}"

echo -e "\n${YELLOW}-Optimizando autoload de Composer...${NC}"
composer dump-autoload
echo -e "${GREEN}-Autoload optimizado.${NC}"

echo -e "\n${YELLOW}-Ejecutando análisis estático con PHPStan y formateo con PHP CS Fixer...${NC}"
vendor/bin/phpstan analyse src tests
vendor/bin/php-cs-fixer fix --dry-run --diff
echo -e "${GREEN}-Análisis y formateo completados.${NC}"

echo -e "\n${YELLOW}-Ejecutando pruebas con Pest...${NC}"
vendor/bin/pest
echo -e "${GREEN}-Pruebas ejecutadas.${NC}"

echo -e "\n${GREEN} 🚀 ¡Proyecto $PROJECT_NAME creado con éxito!${NC}\n"
echo -e "\n${YELLOW}Siguiente paso: cd $PROJECT_NAME:${NC}\n"
echo -e "   1. npm run dev (para iniciar la compilación de Tailwind CSS en modo desarrollo)"
echo -e "   2. Configura tu base de datos en el archivo .env"
echo -e "   3. ¡Empieza a desarrollar tu aplicación!"
exit 0