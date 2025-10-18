<?php

/**
 * helpers.php
 *
 * Conjunto de funciones auxiliares globales para el framework BootZen.
 * Incluye utilidades para depuración, obtención de variables de entorno y generación de URLs absolutas.
 * Facilita el desarrollo y la portabilidad de código en controladores, servicios y vistas.
 *
 * @package    BootZen\Helpers
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.6
 * @since      1.0.0
 */

declare(strict_types=1);

// Polyfills para funciones aleatorias si no existen en el entorno
if (!function_exists('random_bytes')) {
    function random_bytes($length)
    {
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}

if (!function_exists('random_int')) {
    function random_int($min, $max)
    {
        return mt_rand($min, $max);
    }
}

if (!function_exists('mt_rand')) {
    function mt_rand($min = 0, $max = null)
    {
        if ($max === null) {
            $max = 2147483647; // Valor máximo típico de getrandmax()
        }
        return rand($min, $max);
    }
}

if (!function_exists('rand')) {
    function rand($min = 0, $max = null)
    {
        if ($max === null) {
            $max = 2147483647; // Valor máximo típico de getrandmax()
        }
        return (int) (microtime(true) * 1000000) % ($max - $min + 1) + $min;
    }
}
// ...existing code...
if (! function_exists("dd")) {
    function dd(mixed ...$args): void
    {
        foreach ($args as $arg) {
            var_dump($arg);
        }

        throw new \RuntimeException('Ejecución detenida por dd().');
    }
}

/*
 * Obtiene el valor de una variable de entorno.
 *
 * @param string $key Nombre de la variable de entorno
 * @param mixed $default Valor por defecto si no existe
 * @return mixed Valor de la variable o valor por defecto
 * @example $db = env('DB_HOST', 'localhost');
 */
if (! function_exists("env")) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return $value;
    }
}

/*
 * Genera una URL absoluta basada en la variable de entorno APP_URL.
 *
 * @param string $path Ruta relativa a concatenar
 * @return string URL absoluta
 * @example $url = url('dashboard');
 */
if (! function_exists("url")) {
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(env('APP_URL', 'http://localhost'), '/');
        $path = ltrim($path, '/');

        return $baseUrl . '/' . $path;
    }
}


/**
 * Obtiene la ruta absoluta al directorio public.
 *
 * @param string $path Ruta relativa dentro de public
 * @return string Ruta absoluta
 * @example public_path('img/logo.png')
 */
if (! function_exists("public_path")) {
    function public_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . '/public/' . ltrim($path, '/');
    }
}


/**
 * Obtiene la ruta absoluta al directorio storage.
 *
 * @param string $path Ruta relativa dentro de storage
 * @return string Ruta absoluta
 * @example storage_path('logs/error.log')
 */
if (! function_exists("storage_path")) {
    function storage_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . '/storage/' . ltrim($path, '/');
    }
}


/**
 * Genera la URL absoluta de un recurso público con versión basada en la fecha de modificación.
 *
 * @param string $path Ruta relativa del recurso
 * @return string URL absoluta con versión
 * @example asset('css/app.css')
 */
if (! function_exists("asset")) {
    function asset(string $path): string
    {
        return url($path) . '?v=' . filemtime(public_path($path));
    }
}


/**
 * Escapa una cadena para salida segura en HTML.
 *
 * @param string $string Cadena a escapar
 * @return string Cadena escapada
 * @example e('<script>')</script>
 */
if (! function_exists("e")) {
    function e(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}


/**
 * Obtiene el token CSRF de la sesión actual, generándolo si no existe.
 *
 * @return string Token CSRF
 * @example $token = csrf_token();
 */
if (! function_exists("csrf_token")) {
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token'])) {
            if (function_exists('random_bytes')) {
                if (function_exists('random_bytes')) {
                    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
                } elseif (function_exists('openssl_random_pseudo_bytes')) {
                    $_SESSION['_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
                } elseif (function_exists('mt_rand')) {
                    $_SESSION['_csrf_token'] = bin2hex(substr(md5(uniqid((string)mt_rand(), true)), 0, 32));
                } else {
                    $_SESSION['_csrf_token'] = bin2hex(substr(md5(uniqid('', true)), 0, 32));
                }
            } elseif (function_exists('openssl_random_pseudo_bytes')) {
                $_SESSION['_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
            } else {
                // Fallback to less secure method if neither function exists
                if (function_exists('mt_rand')) {
                    $_SESSION['_csrf_token'] = bin2hex(substr(md5(uniqid((string)mt_rand(), true)), 0, 32));
                } else {
                    $_SESSION['_csrf_token'] = bin2hex(substr(md5(uniqid('', true)), 0, 32));
                }
            }
        }

        return $_SESSION['_csrf_token'];
    }
}


/**
 * Genera el campo oculto HTML para el token CSRF.
 *
 * @return string Campo input HTML
 * @example echo csrf_field();
 */
if (! function_exists("csrf_field")) {
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_csrf_token" value="' . e($token) . '"/>';
    }
}


/**
 * Recupera el valor anterior de un campo de formulario desde la sesión.
 *
 * @param string $key Nombre del campo
 * @param mixed $default Valor por defecto si no existe
 * @return mixed Valor anterior o por defecto
 * @example old('email')
 */
if (! function_exists("old")) {
    function old(string $key, mixed $default = ''): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['_old_input'][$key] ?? $default;
    }
}


/**
 * Redirige a una URL específica y termina la ejecución.
 *
 * @param string $url URL de destino
 * @param int $status Código de estado HTTP
 * @return void
 * @example redirect('/login');
 */
if (! function_exists("redirect")) {
    function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}


/**
 * Termina la ejecución mostrando una vista de error o mensaje personalizado.
 *
 * @param int $statusCode Código de estado HTTP
 * @param string $message Mensaje de error
 * @return void
 * @example abort(404, 'Página no encontrada');
 */
if (! function_exists("abort")) {
    function abort(int $statusCode = 404, string $message = ''): void
    {
        http_response_code($statusCode);

        $view = dirname(__DIR__, 2) . "/resources/views/errors/{$statusCode}.php";
        if (file_exists($view)) {
            require $view;
        } else {
            echo e($message ?: 'Error ' . $statusCode);
        }
        exit;
    }
}


/**
 * Registra un mensaje en el archivo de logs diario.
 *
 * @param string $message Mensaje a registrar
 * @param string $level Nivel de log (info, error, etc.)
 * @return void
 * @example logger('Usuario creado', 'info');
 */
if (! function_exists("logger")) {
    function logger(string $message, string $level = 'info'): void
    {
        $logFile = storage_path("logs/" . date('Y-m-d') . ".log");
        $date = date('Y-m-d H:i:s');
        $formattedMessage = "[$date] [$level] $message" . PHP_EOL;
        file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
    }
}


/**
 * Formatea una cantidad de bytes en una cadena legible (KB, MB, etc.).
 *
 * @param int $bytes Cantidad de bytes
 * @param int $precision Decimales
 * @return string Cadena formateada
 * @example format_bytes(2048)
 */
if (! function_exists("format_bytes")) {
    function format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        return sprintf("%.{$precision}f", $bytes / pow(1024, $factor)) . ' ' . $units[(int)$factor];
    }
}


/**
 * Genera una cadena aleatoria alfanumérica.
 *
 * @param int $length Longitud de la cadena
 * @return string Cadena aleatoria
 * @example str_random(8)
 */
if (! function_exists("str_random")) {
    function str_random(int $length = 16): string
    {
        if ($length <= 0) {
            return '';
        }

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            if (function_exists('random_int')) {
                $randomString .= $characters[random_int(0, $charactersLength - 1)];
            } elseif (function_exists('mt_rand')) {
                $randomString .= $characters[mt_rand(0, $charactersLength - 1)];
            } else {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
        }

        return $randomString;
    }
}


/**
 * Convierte un texto en un slug URL amigable.
 *
 * @param string $text Texto a convertir
 * @return string Slug generado
 * @example slugify('Hola Mundo!')
 */
if (! function_exists("slugify")) {
    function slugify(string $text): string
    {
        // Reemplazar espacios y caracteres no alfanuméricos por guiones
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        // Transliterate a ASCII
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        // Eliminar caracteres no deseados
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Convertir a minúsculas
        $text = mb_strtolower($text, 'UTF-8');
        // Eliminar guiones al inicio y al final
        $text = trim($text, '-');
        // Eliminar caracteres no deseados
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);
        // Reemplazar múltiples guiones por uno solo
        $text = preg_replace('/-+/', '-', $text);
        // Eliminar guiones al inicio y al final
        $text = trim($text, '-');
        // Convertir a minúsculas
        $text = strtolower($text);

        return $text ?: 'n-a';
    }
}


/**
 * Aplana un arreglo multidimensional en uno simple.
 *
 * @param array $array Arreglo a aplanar
 * @return array Arreglo plano
 * @example array_flatten([[1,2],[3,4]])
 */
if (! function_exists("array_flatten")) {
    function array_flatten(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function ($item) use (&$result) {
            $result[] = $item;
        });
        return $result;
    }
}


/**
 * Agrupa los elementos de un arreglo según una función clave.
 *
 * @param array $array Arreglo a agrupar
 * @param callable $keySelector Función para obtener la clave
 * @return array Arreglo agrupado
 * @example array_group_by($users, fn($u) => $u['role'])
 */
if (! function_exists("array_group_by")) {
    function array_group_by(array $array, callable $keySelector): array
    {
        $result = [];
        foreach ($array as $item) {
            $key = $keySelector($item);
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $result[$key][] = $item;
        }
        return $result;
    }
}


/**
 * Envía una respuesta JSON y termina la ejecución.
 *
 * @param mixed $data Datos a enviar
 * @param int $status Código de estado HTTP
 * @param array $headers Encabezados adicionales
 * @return void
 * @example json_response(['ok' => true])
 */
if (! function_exists("json_response")) {
    function json_response(mixed $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }
        echo json_encode($data);
        exit;
    }
}


/**
 * Verifica si la petición actual es AJAX.
 *
 * @return bool True si es AJAX, false si no
 * @example if (is_ajax()) { ... }
 */
if (! function_exists("is_ajax")) {
    function is_ajax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}


/**
 * Obtiene la IP del cliente considerando proxies y cabeceras.
 *
 * @return string IP del cliente o 'unknown'
 * @example get_client_ip()
 */
if (! function_exists("get_client_ip")) {
    function get_client_ip(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // Eliminar espacios en blanco
                    if (
                        filter_var(
                            $ip,
                            FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                        ) !== false
                    ) {
                        return $ip;
                    }
                }
            }
        }

        return 'unknown';
    }
}


/**
 * Obtiene el tipo MIME de un archivo.
 *
 * @param string $filePath Ruta al archivo
 * @return string Tipo MIME
 * @example mime_type('archivo.pdf')
 */
if (! function_exists("mime_type")) {
    function mime_type(string $filePath): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }
}

/**
 * Fuerza la descarga de un archivo al cliente.
 *
 * @param string $filePath Ruta al archivo
 * @param string|null $fileName Nombre para el archivo descargado
 * @return void
 * @example download('/tmp/archivo.txt', 'descarga.txt')
 */
if (! function_exists("download")) {
    function download(string $filePath, ?string $fileName = null): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        if ($fileName === null) {
            $fileName = basename($filePath);
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . mime_type($filePath));
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}


/**
 * Valida el token CSRF recibido contra el almacenado en sesión.
 *
 * @param string|null $token Token recibido
 * @return bool True si es válido, false si no
 * @example validate_csrf_token($_POST['_csrf_token'])
 */
if (! function_exists("validate_csrf_token")) {
    function validate_csrf_token(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($token) || empty($_SESSION['_csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}
