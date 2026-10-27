<?php

/**
 * ExceptionHandler.php
 *
 * Convierte excepciones en respuestas HTTP y registra en el log las que lo ameritan.
 *
 * - Petición que espera JSON: respuesta JSON con error, mensaje y estado.
 * - APP_DEBUG activo y error 5xx: página de depuración con traza y fragmento de código.
 * - En otro caso: vista resources/views/errors/{estado}.php, luego errors/default.php,
 *   y si no existen, una página mínima integrada.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

use Throwable;

/**
 * Manejador central de excepciones.
 */
class ExceptionHandler
{
    /**
     * @param Logger|null $logger Logger para report(); null para no registrar
     * @param string $viewsPath Directorio de vistas (resources/views)
     * @param bool $debug Mostrar detalles de las excepciones
     */
    public function __construct(
        private readonly ?Logger $logger,
        private readonly string $viewsPath,
        private readonly bool $debug = false
    ) {
    }

    /**
     * Registra la excepción en el log. Los errores HTTP 4xx no se registran: son flujo normal.
     */
    public function report(Throwable $e, ?Request $request = null): void
    {
        if ($this->logger === null || ($e instanceof HttpException && $e->getStatusCode() < 500)) {
            return;
        }

        $this->logger->error($e->getMessage() !== '' ? $e->getMessage() : $e::class, array_filter([
            'exception' => $e,
            'method' => $request?->getMethod(),
            'uri' => $request?->getUri(),
        ]));
    }

    /**
     * Convierte la excepción en una respuesta.
     */
    public function render(Throwable $e, ?Request $request = null): Response
    {
        $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
        $headers = $e instanceof HttpException ? $e->getHeaders() : [];
        $title = (new Response($status))->getStatusText();
        $message = $this->publicMessage($e, $title);

        if ($request !== null && $request->wantsJson()) {
            return Response::json($this->jsonPayload($e, $status, $title, $message), $status)->withHeaders($headers);
        }

        if ($this->debug && $status >= 500) {
            return Response::html($this->debugPage($e, $request), $status)->withHeaders($headers);
        }

        return Response::html($this->errorPage($status, $title, $message), $status)->withHeaders($headers);
    }

    /**
     * Mensaje que se puede mostrar al usuario sin filtrar detalles internos.
     */
    private function publicMessage(Throwable $e, string $title): string
    {
        if ($e instanceof HttpException) {
            return $e->getMessage() !== '' ? $e->getMessage() : $title;
        }

        return $this->debug ? $e->getMessage() : 'Ocurrió un error inesperado. Inténtalo de nuevo más tarde.';
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Throwable $e, int $status, string $title, string $message): array
    {
        $payload = ['error' => $title, 'message' => $message, 'status' => $status];

        if ($this->debug && $status >= 500) {
            $payload['exception'] = $e::class;
            $payload['file'] = $e->getFile();
            $payload['line'] = $e->getLine();
            $payload['trace'] = explode("\n", $e->getTraceAsString());
        }

        return $payload;
    }

    /**
     * Renderiza la vista de error de la aplicación o la página integrada.
     */
    private function errorPage(int $status, string $title, string $message): string
    {
        foreach (["errors/{$status}.php", 'errors/default.php'] as $view) {
            $file = rtrim($this->viewsPath, '/') . '/' . $view;
            if (! is_file($file)) {
                continue;
            }

            try {
                return self::renderFile($file, ['status' => $status, 'title' => $title, 'message' => $message]);
            } catch (Throwable) {
                // Si la vista de error falla, se usa la página integrada
                break;
            }
        }

        return sprintf(
            '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>%1$d %2$s</title>'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0">'
            . '<main style="text-align:center"><h1>%1$d - %2$s</h1><p>%3$s</p></main></body></html>',
            $status,
            htmlspecialchars($title),
            htmlspecialchars($message)
        );
    }

    /**
     * Incluye un archivo PHP con variables y devuelve su salida.
     *
     * @param array<string, mixed> $data
     */
    private static function renderFile(string $file, array $data): string
    {
        $level = ob_get_level();
        ob_start();

        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }

    /**
     * Página de depuración: excepción, fragmento de código, traza y datos de la petición.
     */
    private function debugPage(Throwable $e, ?Request $request): string
    {
        $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $chain = '';
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $chain .= sprintf(
                '<section><h2>%s</h2><p class="msg">%s</p><p class="loc">%s:%d</p>%s<pre>%s</pre></section>',
                $h($current::class),
                $h($current->getMessage()),
                $h($current->getFile()),
                $current->getLine(),
                $this->codeExcerpt($current->getFile(), $current->getLine()),
                $h($current->getTraceAsString())
            );
        }

        $requestInfo = $request === null ? '' : sprintf(
            '<section><h2>Petición</h2><pre>%s %s</pre></section>',
            $h($request->getMethod()),
            $h($request->getUri())
        );

        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Error: ' . $h($e::class) . '</title>'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><style>'
            . 'body{margin:0;padding:2rem;font-family:system-ui,sans-serif;background:#0f172a;color:#e2e8f0}'
            . 'section{background:#1e293b;border-radius:.75rem;padding:1.5rem;margin-bottom:1.5rem}'
            . 'h1{color:#f87171;margin-top:0}h2{margin:0 0 .5rem;color:#fca5a5;font-size:1.1rem}'
            . '.msg{font-size:1.25rem}.loc{color:#94a3b8}pre{overflow:auto;font-size:.85rem;line-height:1.5}'
            . 'ol{margin:0;padding:0;list-style:none;font-family:monospace;font-size:.85rem;overflow:auto}'
            . 'li{white-space:pre}li.hl{background:#7f1d1d}'
            . '</style></head><body><h1>Error de la aplicación</h1>' . $chain . $requestInfo
            . '<p style="color:#94a3b8">Esta página solo se muestra con APP_DEBUG=true.</p></body></html>';
    }

    /**
     * Fragmento de código alrededor de la línea del error.
     */
    private function codeExcerpt(string $file, int $line): string
    {
        $lines = is_file($file) ? file($file) : false;
        if ($lines === false) {
            return '';
        }

        $start = max(1, $line - 6);
        $end = min(count($lines), $line + 6);
        $html = '';

        for ($number = $start; $number <= $end; $number++) {
            $html .= sprintf(
                '<li%s>%4d  %s</li>',
                $number === $line ? ' class="hl"' : '',
                $number,
                htmlspecialchars(rtrim($lines[$number - 1]), ENT_QUOTES, 'UTF-8')
            );
        }

        return '<ol>' . $html . '</ol>';
    }
}
