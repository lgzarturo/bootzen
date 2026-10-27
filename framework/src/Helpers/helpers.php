<?php

/**
 * helpers.php
 *
 * Conjunto de funciones auxiliares globales para el framework BootZen.
 * Incluye utilidades para depuración, variables de entorno, rutas, URLs, seguridad y respuestas.
 *
 * Ningún helper termina la ejecución con exit: los que generan respuestas devuelven un Response
 * y los que cortan el flujo lanzan una excepción que Application convierte en respuesta.
 *
 * @package    BootZen\Helpers
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      1.0.0
 */

declare(strict_types=1);

use BootZen\Core\Application;
use BootZen\Core\Cache;
use BootZen\Core\HttpException;
use BootZen\Core\Logger;
use BootZen\Core\Response;
use BootZen\View\View;

if (! function_exists('dd')) {
    /**
     * Vuelca los valores recibidos y detiene la ejecución lanzando una excepción.
     *
     * @example dd($user, $request);
     */
    function dd(mixed ...$args): never
    {
        foreach ($args as $arg) {
            var_dump($arg);
        }

        throw new \RuntimeException('Ejecución detenida por dd().');
    }
}

if (! function_exists('env')) {
    /**
     * Obtiene el valor de una variable de entorno.
     *
     * Los textos 'true', 'false', 'null' y 'empty' (con o sin paréntesis) se convierten a
     * true, false, null y '' respectivamente.
     *
     * @param string $key Nombre de la variable de entorno
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor de la variable o valor por defecto
     * @example $debug = env('APP_DEBUG', false);
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = array_key_exists($key, $_ENV) ? $_ENV[$key] : getenv($key);

        if ($value === false) {
            return $default;
        }

        if (! is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (! function_exists('base_path')) {
    /**
     * Obtiene la ruta absoluta a la raíz del proyecto.
     *
     * Usa la raíz de la Application actual o, si aún no existe, la constante BOOTZEN_BASE_PATH
     * que define framework/bootstrap.php.
     *
     * @param string $path Ruta relativa dentro del proyecto
     * @return string Ruta absoluta
     * @example base_path('database/app.sqlite')
     */
    function base_path(string $path = ''): string
    {
        if (Application::hasInstance()) {
            return Application::getInstance()->basePath($path);
        }

        $base = defined('BOOTZEN_BASE_PATH') ? (string) constant('BOOTZEN_BASE_PATH') : dirname(__DIR__, 3);

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (! function_exists('url')) {
    /**
     * Genera una URL absoluta basada en config('app.url') o, en su defecto, APP_URL.
     *
     * @param string $path Ruta relativa a concatenar
     * @return string URL absoluta
     * @example $url = url('dashboard');
     */
    function url(string $path = ''): string
    {
        $appUrl = Application::hasInstance()
            ? Application::getInstance()->config()->get('app.url', env('APP_URL', 'http://localhost'))
            : env('APP_URL', 'http://localhost');
        $baseUrl = rtrim(is_scalar($appUrl) ? (string) $appUrl : 'http://localhost', '/');

        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (! function_exists('public_path')) {
    /**
     * Obtiene la ruta absoluta al directorio public.
     *
     * @param string $path Ruta relativa dentro de public
     * @return string Ruta absoluta
     * @example public_path('img/logo.png')
     */
    function public_path(string $path = ''): string
    {
        return base_path('public/' . ltrim($path, '/'));
    }
}

if (! function_exists('storage_path')) {
    /**
     * Obtiene la ruta absoluta al directorio storage.
     *
     * @param string $path Ruta relativa dentro de storage
     * @return string Ruta absoluta
     * @example storage_path('logs/error.log')
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage/' . ltrim($path, '/'));
    }
}

if (! function_exists('asset')) {
    /**
     * Genera la URL de un recurso público con versión basada en la fecha de modificación.
     *
     * @param string $path Ruta relativa del recurso dentro de public
     * @return string URL absoluta, con ?v= si el archivo existe
     * @example asset('assets/app.css')
     */
    function asset(string $path): string
    {
        $file = public_path($path);
        $version = is_file($file) ? filemtime($file) : false;

        return $version === false ? url($path) : url($path) . '?v=' . $version;
    }
}

if (! function_exists('e')) {
    /**
     * Escapa un valor para salida segura en HTML.
     *
     * @param string|int|float|bool|\Stringable|null $value Valor a escapar
     * @return string Cadena escapada
     * @example e('<script>')
     */
    function e(string|int|float|bool|\Stringable|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('csrf_token')) {
    /**
     * Obtiene el token CSRF de la sesión actual, generándolo si no existe.
     *
     * @return string Token CSRF
     * @example $token = csrf_token();
     */
    function csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['_csrf_token']) || ! is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (! function_exists('csrf_field')) {
    /**
     * Genera el campo oculto HTML para el token CSRF.
     *
     * @return string Campo input HTML
     * @example echo csrf_field();
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '"/>';
    }
}

if (! function_exists('validate_csrf_token')) {
    /**
     * Valida el token CSRF recibido contra el almacenado en sesión.
     *
     * @param string|null $token Token recibido
     * @return bool True si es válido, false si no
     * @example validate_csrf_token($request->getBody('_csrf_token'))
     */
    function validate_csrf_token(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $stored = $_SESSION['_csrf_token'] ?? null;

        if ($token === null || $token === '' || ! is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }
}

if (! function_exists('old')) {
    /**
     * Recupera el valor anterior de un campo de formulario desde la sesión.
     *
     * @param string $key Nombre del campo
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor anterior o por defecto
     * @example old('email')
     */
    function old(string $key, mixed $default = ''): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $old = $_SESSION['_old_input'] ?? [];

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (! function_exists('redirect')) {
    /**
     * Crea una respuesta de redirección. Debe retornarse desde el handler.
     *
     * @param string $url URL de destino
     * @param int $status Código de estado HTTP
     * @return Response Respuesta de redirección
     * @example return redirect('/login');
     */
    function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}

if (! function_exists('abort')) {
    /**
     * Interrumpe la petición lanzando una HttpException con el código indicado.
     *
     * @param int $statusCode Código de estado HTTP
     * @param string $message Mensaje de error
     * @param array<string, string> $headers Cabeceras adicionales
     * @throws HttpException Siempre
     * @example abort(404, 'Página no encontrada');
     */
    function abort(int $statusCode = 404, string $message = '', array $headers = []): never
    {
        throw new HttpException($statusCode, $message, $headers);
    }
}

if (! function_exists('logger')) {
    /**
     * Registra un mensaje en el log de la aplicación (storage/logs/app-AAAA-MM-DD.log).
     *
     * @param string $message Mensaje; admite marcadores {clave} que se reemplazan con el contexto
     * @param string $level Nivel PSR-3: debug, info, notice, warning, error, critical, alert, emergency
     * @param array<string, mixed> $context Datos adicionales
     * @example logger('Usuario {id} creado', 'info', ['id' => $user->id]);
     */
    function logger(string $message, string $level = 'info', array $context = []): void
    {
        $logger = Application::hasInstance()
            ? Application::getInstance()->make(Logger::class)
            : new Logger(storage_path('logs'));

        if ($logger instanceof Logger) {
            $logger->log($level, $message, $context);
        }
    }
}

if (! function_exists('app')) {
    /**
     * Devuelve la Application actual o resuelve un servicio del contenedor.
     *
     * @param string|null $abstract Servicio a resolver; null para la Application
     * @example $router = app(Router::class);
     */
    function app(?string $abstract = null): mixed
    {
        $app = Application::getInstance();

        return $abstract === null ? $app : $app->make($abstract);
    }
}

if (! function_exists('config')) {
    /**
     * Obtiene un valor de configuración con notación de puntos.
     *
     * @param string $key Clave, por ejemplo 'app.name'
     * @param mixed $default Valor si la clave no existe
     * @example config('app.timezone', 'UTC')
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()->config()->get($key, $default);
    }
}

if (! function_exists('route')) {
    /**
     * Genera el path de una ruta con nombre. Los parámetros sobrantes se agregan como query string.
     *
     * @param string $name Nombre de la ruta
     * @param array<string, string|int|float|bool|\Stringable> $parameters Parámetros de la ruta
     * @return string Path relativo, por ejemplo '/tareas/5'; usa url(route(...)) para una URL absoluta
     * @example route('tareas.show', ['id' => 5])
     */
    function route(string $name, array $parameters = []): string
    {
        return Application::getInstance()->getRouter()->url($name, $parameters);
    }
}

if (! function_exists('view')) {
    /**
     * Renderiza una vista y la devuelve como respuesta HTML.
     *
     * @param string $name Nombre con puntos: 'tareas.index' → resources/views/tareas/index.php
     * @param array<string, mixed> $data Variables para la vista
     * @param int $status Código de estado HTTP
     * @example return view('tareas.index', ['tareas' => $tareas]);
     */
    function view(string $name, array $data = [], int $status = 200): Response
    {
        $view = Application::getInstance()->make(View::class);

        if (! $view instanceof View) {
            throw new \LogicException('El servicio View debe ser una instancia de ' . View::class . '.');
        }

        return Response::html($view->render($name, $data), $status);
    }
}

if (! function_exists('class_list')) {
    /**
     * Arma una lista de clases CSS con condiciones, útil para Tailwind en las vistas.
     *
     * @param array<int|string, mixed> $classes Clases fijas y 'clase' => condición
     * @example <a class="{{ class_list(['px-2', 'text-blue-600' => $activo]) }}">
     */
    function class_list(array $classes): string
    {
        return \BootZen\View\ComponentAttributeBag::classList($classes);
    }
}

if (! function_exists('cache')) {
    /**
     * Devuelve la caché de archivos de la aplicación.
     *
     * @example cache()->remember('stats', 600, fn () => calcularStats());
     */
    function cache(): Cache
    {
        $cache = Application::getInstance()->make(Cache::class);

        if (! $cache instanceof Cache) {
            throw new \LogicException('El servicio Cache debe ser una instancia de ' . Cache::class . '.');
        }

        return $cache;
    }
}

if (! function_exists('format_bytes')) {
    /**
     * Formatea una cantidad de bytes en una cadena legible (KB, MB, etc.).
     *
     * @param int $bytes Cantidad de bytes
     * @param int $precision Decimales
     * @return string Cadena formateada
     * @example format_bytes(2048)
     */
    function format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return sprintf("%.{$precision}f", $bytes / (1024 ** $factor)) . ' ' . $units[$factor];
    }
}

if (! function_exists('str_random')) {
    /**
     * Genera una cadena aleatoria alfanumérica criptográficamente segura.
     *
     * @param int $length Longitud de la cadena
     * @return string Cadena aleatoria
     * @example str_random(8)
     */
    function str_random(int $length = 16): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($characters) - 1;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, $max)];
        }

        return $result;
    }
}

if (! function_exists('slugify')) {
    /**
     * Convierte un texto en un slug apto para URLs.
     *
     * @param string $text Texto a convertir
     * @return string Slug generado, o 'n-a' si queda vacío
     * @example slugify('Hola Mundo!')
     */
    function slugify(string $text): string
    {
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = strtolower($ascii === false ? $text : $ascii);
        $text = (string) preg_replace('/[^a-z0-9-]/', '', $text);
        $text = trim((string) preg_replace('/-+/', '-', $text), '-');

        return $text !== '' ? $text : 'n-a';
    }
}

if (! function_exists('array_flatten')) {
    /**
     * Aplana un arreglo multidimensional en uno simple.
     *
     * @param array<mixed> $array Arreglo a aplanar
     * @return array<int, mixed> Arreglo plano
     * @example array_flatten([[1,2],[3,4]])
     */
    function array_flatten(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function (mixed $item) use (&$result): void {
            $result[] = $item;
        });

        return $result;
    }
}

if (! function_exists('array_group_by')) {
    /**
     * Agrupa los elementos de un arreglo según una función clave.
     *
     * @param array<mixed> $array Arreglo a agrupar
     * @param callable(mixed): (int|string) $keySelector Función para obtener la clave
     * @return array<int|string, array<int, mixed>> Arreglo agrupado
     * @example array_group_by($users, fn($u) => $u['role'])
     */
    function array_group_by(array $array, callable $keySelector): array
    {
        $result = [];
        foreach ($array as $item) {
            $result[$keySelector($item)][] = $item;
        }

        return $result;
    }
}

if (! function_exists('json_response')) {
    /**
     * Crea una respuesta JSON. Debe retornarse desde el handler.
     *
     * @param array<mixed> $data Datos a enviar
     * @param int $status Código de estado HTTP
     * @param array<string, string> $headers Encabezados adicionales
     * @return Response Respuesta JSON
     * @example return json_response(['ok' => true]);
     */
    function json_response(array $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }
}

if (! function_exists('is_ajax')) {
    /**
     * Verifica si la petición actual es AJAX.
     *
     * @return bool True si es AJAX, false si no
     * @example if (is_ajax()) { ... }
     */
    function is_ajax(): bool
    {
        $header = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return is_string($header) && strtolower($header) === 'xmlhttprequest';
    }
}

if (! function_exists('get_client_ip')) {
    /**
     * Obtiene la IP pública del cliente considerando proxies y cabeceras.
     *
     * @return string IP del cliente o 'unknown'
     * @example get_client_ip()
     */
    function get_client_ip(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ipKeys as $key) {
            $value = $_SERVER[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            foreach (explode(',', $value) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }
}

if (! function_exists('mime_type')) {
    /**
     * Obtiene el tipo MIME de un archivo.
     *
     * @param string $filePath Ruta al archivo
     * @return string Tipo MIME
     * @example mime_type('archivo.pdf')
     */
    function mime_type(string $filePath): string
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return 'application/octet-stream';
        }

        $mimeType = mime_content_type($filePath);

        return $mimeType !== false ? $mimeType : 'application/octet-stream';
    }
}

if (! function_exists('download')) {
    /**
     * Crea una respuesta que fuerza la descarga de un archivo. Debe retornarse desde el handler.
     *
     * @param string $filePath Ruta al archivo
     * @param string|null $fileName Nombre para el archivo descargado
     * @return Response Respuesta con el contenido del archivo
     * @throws HttpException 404 si el archivo no existe o no se puede leer
     * @example return download(storage_path('exports/reporte.csv'));
     */
    function download(string $filePath, ?string $fileName = null): Response
    {
        $contents = is_file($filePath) && is_readable($filePath) ? file_get_contents($filePath) : false;

        if ($contents === false) {
            throw new HttpException(404, 'File not found.');
        }

        return new Response(200, $contents, [
            'content-type' => mime_type($filePath),
            'content-disposition' => 'attachment; filename="' . basename($fileName ?? $filePath) . '"',
            'content-length' => (string) strlen($contents),
            'cache-control' => 'must-revalidate',
        ]);
    }
}
