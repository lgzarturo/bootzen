#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Class CodeCounter
 * Cuenta líneas de código, comentarios y líneas en blanco en archivos PHP, JS, CSS y HTML.
 */
class CodeCounter
{
    private array $stats = [
        'php' => ['files' => 0, 'lines' => 0, 'code' => 0, 'comments' => 0, 'blank' => 0],
        'js' => ['files' => 0, 'lines' => 0, 'code' => 0, 'comments' => 0, 'blank' => 0],
        'css' => ['files' => 0, 'lines' => 0, 'code' => 0, 'comments' => 0, 'blank' => 0],
        'html' => ['files' => 0, 'lines' => 0, 'code' => 0, 'comments' => 0, 'blank' => 0],
    ];
    
    private array $excludeDirs = ['vendor', 'node_modules', '.git', 'storage', 'cache'];
    
    /**
     * Inicia el conteo de líneas en el directorio especificado.
     *
     * @param string $directory Directorio raíz a analizar (por defecto '.').
     * @return void
     */
    public function count(string $directory = '.'): void
    {
        $this->scanDirectory($directory);
        $this->displayResults();
    }
    
    /**
     * Escanea recursivamente el directorio en busca de archivos soportados.
     *
     * @param string $dir Directorio a escanear.
     * @return void
     */
    private function scanDirectory(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && !$this->shouldExclude($file->getPathname())) {
                $this->processFile($file);
            }
        }
    }
    
    /**
     * Determina si el archivo debe ser excluido según los directorios configurados.
     *
     * @param string $path Ruta completa del archivo.
     * @return bool Verdadero si debe excluirse, falso en caso contrario.
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludeDirs as $exclude) {
            if (strpos($path, DIRECTORY_SEPARATOR . $exclude . DIRECTORY_SEPARATOR) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Procesa un archivo individual, contando líneas de código, comentarios y blancas.
     *
     * @param SplFileInfo $file Archivo a procesar.
     * @return void
     */
    private function processFile(SplFileInfo $file): void
    {
        $extension = $file->getExtension();
        $type = match($extension) {
            'php' => 'php',
            'js', 'jsx' => 'js',
            'css', 'scss' => 'css',
            'html', 'htm' => 'html',
            default => null
        };
        
        if (!$type) return;
        
        $content = file_get_contents($file->getPathname());
        $lines = explode("\n", $content);
        
        $this->stats[$type]['files']++;
        $this->stats[$type]['lines'] += count($lines);
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (empty($trimmed)) {
                $this->stats[$type]['blank']++;
            } elseif ($this->isComment($trimmed, $type)) {
                $this->stats[$type]['comments']++;
            } else {
                $this->stats[$type]['code']++;
            }
        }
    }
    
    /**
     * Determina si una línea es un comentario según el tipo de archivo.
     *
     * @param string $line Línea de texto a analizar.
     * @param string $type Tipo de archivo (php, js, css, html).
     * @return bool Verdadero si la línea es comentario, falso en caso contrario.
     */
    private function isComment(string $line, string $type): bool
    {
        return match($type) {
            'php' => str_starts_with($line, '//') || str_starts_with($line, '#') || 
                     str_starts_with($line, '/*') || str_starts_with($line, '*'),
            'js' => str_starts_with($line, '//') || str_starts_with($line, '/*') || 
                    str_starts_with($line, '*'),
            'css' => str_starts_with($line, '/*') || str_starts_with($line, '*'),
            'html' => str_starts_with($line, '<!--'),
            default => false
        };
    }
    
    /**
     * Muestra los resultados del conteo en formato de tabla con colores ANSI.
     *
     * @return void
     */
    private function displayResults(): void
    {
        // Colores ANSI
        $colors = [
            'php'  => "\033[1;36m", // azul cielo
            'js'   => "\033[1;33m", // amarillo
            'css'  => "\033[38;5;208m", // naranja
            'html' => "\033[1;32m", // verde lima
            'reset'=> "\033[0m"
        ];

        echo "\n╔══════════════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║   Lenguaje   │  Archivos  │  Líneas  │  Código  │ Comentarios │  Blancas  │ % Código ║\n";
        echo "╟──────────────┼────────────┼──────────┼──────────┼─────────────┼───────────┼──────────╢\n";

        $totals = ['files' => 0, 'lines' => 0, 'code' => 0, 'comments' => 0, 'blank' => 0];

        foreach ($this->stats as $lang => $stats) {
            if ($stats['files'] > 0) {
                $color = $colors[$lang] ?? $colors['reset'];
                $ratio = ($stats['code'] / max($stats['lines'], 1)) * 100;
                printf(
                    "║ %s%-10s%s   │ %9d  │ %8d │ %8d │ %11d │ %9d │ %7.1f%% ║\n",
                    $color,
                    strtoupper($lang),
                    $colors['reset'],
                    $stats['files'],
                    $stats['lines'],
                    $stats['code'],
                    $stats['comments'],
                    $stats['blank'],
                    $ratio
                );
                echo "╟──────────────┼────────────┼──────────┼──────────┼─────────────┼───────────┼──────────╢\n";
                foreach ($stats as $key => $value) {
                    $totals[$key] += $value;
                }
            }
        }

        $totalRatio = ($totals['code'] / max($totals['lines'], 1)) * 100;
        // Color para el total: blanco intenso
        $totalColor = "\033[1;37m";
        printf(
            "║ %s%-10s%s   │ %9d  │ %8d │ %8d │ %11d │ %9d │ %7.1f%% ║\n",
            $totalColor,
            'TOTAL',
            $colors['reset'],
            $totals['files'],
            $totals['lines'],
            $totals['code'],
            $totals['comments'],
            $totals['blank'],
            $totalRatio
        );
        echo "╚══════════════════════════════════════════════════════════════════════════════════════╝\n\n";
    }
}

/**
 * Muestra la documentación de uso del script.
 */
function mostrarAyuda(): void {
    echo "\nUSO: ./count_lines.sh [directorio]\n";
    echo "---------------------------------------------\n";
    echo "Este script cuenta líneas de código, comentarios y líneas en blanco en archivos:\n";
    echo "  - PHP, JS, CSS, HTML\n";
    echo "Excluye carpetas comunes como vendor, node_modules, .git, storage y cache.\n\n";
    echo "Parámetros:\n";
    echo "  [directorio]   Directorio raíz a analizar (opcional, por defecto el padre del script)\n";
    echo "  -h, --help     Muestra esta ayuda y termina\n\n";
    echo "Ejemplo:\n";
    echo "  ./count_lines.sh src\n";
    echo "  ./count_lines.sh --help\n\n";
    echo "Resultado:\n";
    echo "  Se muestra una tabla con el total de archivos, líneas, código, comentarios y líneas en blanco por lenguaje.\n\n";
}

// Procesar argumentos de línea de comandos
$args = $argv;
array_shift($args); // El primer argumento es el nombre del script

if (in_array('--help', $args) || in_array('-h', $args)) {
    mostrarAyuda();
    exit(0);
}

$directorio = $args[0] ?? dirname(__DIR__);

// Ejecutar contador
$counter = new CodeCounter();
$counter->count($directorio);