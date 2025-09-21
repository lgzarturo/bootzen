#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Class CodeAnalyzer
 * Analiza código PHP en busca de problemas de calidad, seguridad y métricas.
 */
class CodeAnalyzer
{
    private array $issues = [];
    private array $metrics = [];
    
    /**
     * Analiza todos los archivos PHP en el directorio especificado.
     *
     * @param string $directory Directorio raíz a analizar (por defecto 'src').
     * @return void
     */
    public function analyze(string $directory = 'src'): void
    {
        echo "\n🔍 Analizando código...\n\n";
        
        $this->scanDirectory($directory);
        $this->displayResults();
    }
    
    /**
     * Escanea recursivamente el directorio en busca de archivos PHP.
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
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->analyzeFile($file);
            }
        }
    }
    
    /**
     * Analiza un archivo PHP individual y ejecuta todas las verificaciones.
     *
     * @param SplFileInfo $file Archivo PHP a analizar.
     * @return void
     */
    private function analyzeFile(SplFileInfo $file): void
    {
        $content = file_get_contents($file->getPathname());
        $tokens = token_get_all($content);
        $relativePath = str_replace(getcwd() . '/', '', $file->getPathname());
        
        $this->checkComplexity($tokens, $relativePath);
        $this->checkNamingConventions($tokens, $relativePath);
        $this->checkSecurityIssues($content, $relativePath);
        $this->checkCodeSmells($tokens, $relativePath);
        $this->calculateMetrics($tokens, $relativePath);
    }
    
    /**
     * Verifica la complejidad ciclomática de las funciones en el archivo.
     *
     * @param array $tokens Tokens del archivo PHP.
     * @param string $file Ruta relativa del archivo.
     * @return void
     */
    private function checkComplexity(array $tokens, string $file): void
    {
        $complexity = 0;
        $functionName = null;
        $inFunction = false;
        
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_FUNCTION) {
                    $inFunction = true;
                    $complexity = 1;
                } elseif ($inFunction && in_array($token[0], [T_IF, T_ELSEIF, T_FOR, T_FOREACH, T_WHILE, T_CASE])) {
                    $complexity++;
                } elseif ($token[0] === T_STRING && $inFunction && !$functionName) {
                    $functionName = $token[1];
                }
            } elseif ($token === '{' && $inFunction && $functionName) {
                // Inicio de la función detectada
            } elseif ($token === '}' && $inFunction) {
                if ($complexity > 10) {
                    $this->issues['complexity'][] = [
                        'file' => $file,
                        'function' => $functionName,
                        'complexity' => $complexity,
                        'severity' => 'warning'
                    ];
                }
                $inFunction = false;
                $functionName = null;
            }
        }
    }
    
    /**
     * Verifica las convenciones de nombres en clases y funciones.
     *
     * @param array $tokens Tokens del archivo PHP.
     * @param string $file Ruta relativa del archivo.
     * @return void
     */
    private function checkNamingConventions(array $tokens, string $file): void
    {
        $crudNames = [
            'index', 'create', 'store', 'show', 'update', 'destroy',
            'all', 'find', 'delete', 'up', 'down', 'process', 'validate',
            '__construct'
        ];
        for ($i = 0; $i < count($tokens); $i++) {
            if (is_array($tokens[$i])) {
                // Validar el nombre de la clase (PascalCase)
                if (
                    $tokens[$i][0] === T_CLASS &&
                    isset($tokens[$i + 2]) &&
                    is_array($tokens[$i + 2]) &&
                    isset($tokens[$i + 2][1])
                ) {
                    $className = $tokens[$i + 2][1];
                    if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $className)) {
                        $this->issues['naming'][] = [
                            'file' => $file,
                            'type' => 'class',
                            'name' => $className,
                            'message' => 'El nombre de la clase debe estar en PascalCase'
                        ];
                    }
                }

                // Validar los nombres de las funciones (camelCase o CRUD simples)
                if (
                    $tokens[$i][0] === T_FUNCTION &&
                    isset($tokens[$i + 2]) &&
                    is_array($tokens[$i + 2]) &&
                    isset($tokens[$i + 2][1])
                ) {
                    $functionName = $tokens[$i + 2][1];
                    if (!preg_match('/^[a-z][a-zA-Z0-9]*$/', $functionName) && !in_array($functionName, $crudNames)) {
                        $this->issues['naming'][] = [
                            'file' => $file,
                            'type' => 'function',
                            'name' => $functionName,
                            'message' => 'El nombre de la función debe estar en camelCase o ser un nombre CRUD simple'
                        ];
                    }
                }
            }
        }
    }
    
    /**
     * Busca posibles vulnerabilidades de seguridad en el contenido del archivo.
     *
     * @param string $content Contenido del archivo PHP.
     * @param string $file Ruta relativa del archivo.
     * @return void
     */
    private function checkSecurityIssues(string $content, string $file): void
    {
        // Validar vulnerabilidades de inyección SQL
        if (preg_match('/\$_(GET|POST|REQUEST)\[.+\].*?(SELECT|INSERT|UPDATE|DELETE)/i', $content)) {
            $this->issues['security'][] = [
                'file' => $file,
                'type' => 'sql_injection',
                'severity' => 'critical',
                'message' => 'Posible vulnerabilidad de inyección SQL detectada'
            ];
        }

        // Validar vulnerabilidades XSS
        if (preg_match('/echo\s+\$_(GET|POST|REQUEST)\[/', $content)) {
            $this->issues['security'][] = [
                'file' => $file,
                'type' => 'xss',
                'severity' => 'high',
                'message' => 'Posible vulnerabilidad XSS - escapar salida'
            ];
        }

        // Validar credenciales codificadas
        if (preg_match('/(password|api_key|secret)\s*=\s*["\'][^"\']+["\']/', $content)) {
            $this->issues['security'][] = [
                'file' => $file,
                'type' => 'hardcoded_credentials',
                'severity' => 'high',
                'message' => 'Posibles credenciales codificadas detectadas'
            ];
        }
    }
    
    /**
     * Detecta code smells como métodos largos y clases grandes.
     *
     * @param array $tokens Tokens del archivo PHP.
     * @param string $file Ruta relativa del archivo.
     * @return void
     */
    private function checkCodeSmells(array $tokens, string $file): void
    {
        $lineCount = 0;
        $methodLines = 0;
        $inMethod = false;
        
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                $lineCount += substr_count($token[1], "\n");
                if ($inMethod) {
                    $methodLines += substr_count($token[1], "\n");
                }
            }
            
            if (is_array($token) && $token[0] === T_FUNCTION) {
                $inMethod = true;
                $methodLines = 0;
            }
            
            if ($token === '}' && $inMethod) {
                if ($methodLines > 50) {
                    $this->issues['smells'][] = [
                        'file' => $file,
                        'type' => 'long_method',
                        'lines' => $methodLines,
                        'message' => 'El método es demasiado largo (> 50 líneas)'
                    ];
                }
                $inMethod = false;
            }
        }
        
        if ($lineCount > 300) {
            $this->issues['smells'][] = [
                'file' => $file,
                'type' => 'large_class',
                'lines' => $lineCount,
                'message' => 'El archivo es demasiado grande (> 300 líneas)'
            ];
        }
    }
    
    /**
     * Calcula métricas básicas del archivo: clases, métodos, propiedades y líneas.
     *
     * @param array $tokens Tokens del archivo PHP.
     * @param string $file Ruta relativa del archivo.
     * @return void
     */
    private function calculateMetrics(array $tokens, string $file): void
    {
        $metrics = [
            'classes' => 0,
            'methods' => 0,
            'properties' => 0,
            'lines' => 0,
        ];
        
        foreach ($tokens as $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_CLASS:
                        $metrics['classes']++;
                        break;
                    case T_FUNCTION:
                        $metrics['methods']++;
                        break;
                    case T_PRIVATE:
                    case T_PROTECTED:
                    case T_PUBLIC:
                        $metrics['properties']++;
                        break;
                }
                
                if ($token[0] === T_WHITESPACE) {
                    $metrics['lines'] += substr_count($token[1], "\n");
                }
            }
        }
        
        $this->metrics[$file] = $metrics;
    }
    
    /**
     * Muestra los resultados del análisis: problemas encontrados y resumen de métricas.
     *
     * @return void
     */
    private function displayResults(): void
    {
        // Display issues
        if (!empty($this->issues)) {
            echo "⚠️  Problemas encontrados:\n";
            echo "═══════════════\n\n";
            
            foreach ($this->issues as $type => $typeIssues) {
                echo "📌 " . strtoupper($type) . ":\n";
                foreach ($typeIssues as $issue) {
                    $severity = $issue['severity'] ?? 'info';
                    $icon = match($severity) {
                        'critical' => '🔴',
                        'high' => '🟠',
                        'warning' => '🟡',
                        default => '🔵'
                    };
                    
                    echo "  $icon {$issue['file']}\n";
                    echo "     → {$issue['message']}\n";
                }
                echo "\n";
            }
        } else {
            echo "✅ No se encontraron problemas!\n\n";
        }
        
        // Display metrics summary
        echo "📊 RESUMEN DE MÉTRICAS:\n";
        echo "═════════════════\n\n";
        
        $totalMetrics = [
            'files' => count($this->metrics),
            'classes' => 0,
            'methods' => 0,
            'lines' => 0,
        ];
        
        foreach ($this->metrics as $file => $metrics) {
            $totalMetrics['classes'] += $metrics['classes'];
            $totalMetrics['methods'] += $metrics['methods'];
            $totalMetrics['lines'] += $metrics['lines'];
        }
        
        echo "  Archivos analizados: {$totalMetrics['files']}\n";
        echo "  Total classes: {$totalMetrics['classes']}\n";
        echo "  Total methods: {$totalMetrics['methods']}\n";
        echo "  Total lines: {$totalMetrics['lines']}\n";
        echo "  Avg methods/class: " . round($totalMetrics['methods'] / max($totalMetrics['classes'], 1), 2) . "\n";
        echo "\n";
    }
}

/**
 * Muestra la documentación de uso del script.
 */
function mostrarAyuda(): void {
    echo "\nUSO: ./analyzer.sh [directorio]\n";
    echo "---------------------------------------------\n";
    echo "Este script analiza archivos PHP en busca de:\n";
    echo "  - Complejidad ciclomática excesiva en funciones\n";
    echo "  - Convenciones de nombres en clases y funciones\n";
    echo "  - Vulnerabilidades de seguridad (SQLi, XSS, credenciales)\n";
    echo "  - Code smells (métodos largos, clases grandes)\n";
    echo "  - Métricas de código (clases, métodos, líneas)\n\n";
    echo "Parámetros:\n";
    echo "  [directorio]   Directorio raíz a analizar (opcional, por defecto 'src')\n";
    echo "  -h, --help     Muestra esta ayuda y termina\n\n";
    echo "Ejemplo:\n";
    echo "  ./analyzer.sh src\n";
    echo "  ./analyzer.sh --help\n\n";
    echo "Resultado:\n";
    echo "  Se muestra un resumen de problemas encontrados y métricas del código analizado.\n\n";
}

// Procesar argumentos de línea de comandos
$args = $argv;
array_shift($args); // El primer argumento es el nombre del script

if (in_array('--help', $args) || in_array('-h', $args)) {
    mostrarAyuda();
    exit(0);
}

$directorio = $args[0] ?? 'src';

// Ejecutar analizador
$analyzer = new CodeAnalyzer();
$analyzer->analyze($directorio);
