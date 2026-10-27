<?php

/**
 * WebInstaller.php
 *
 * Instalación desde el navegador para hostings sin terminal: /_bootzen/instalar pide la APP_KEY y
 * ejecuta las migraciones pendientes. Está apagado por defecto (INSTALL_ENABLED=true lo activa) y,
 * después de una instalación correcta, se bloquea solo: desde ahí responde 404.
 *
 * @package    BootZen\Deploy
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Deploy;

use BootZen\Core\Application;
use BootZen\Core\HttpException;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Database\Migrator;
use BootZen\Security\RateLimiter;
use Throwable;

/**
 * La ruta la registra Application solo si config('app.installer') es true. No usa sesión ni CSRF:
 * la APP_KEY hace de contraseña y hay un límite de 5 intentos cada 10 minutos por IP.
 */
final class WebInstaller
{
    public const PATH = '/_bootzen/instalar';

    public const LOCK_FILE = 'storage/install.lock';

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly Application $app, private readonly RateLimiter $limiter)
    {
    }

    public function handle(Request $request): Response
    {
        if (is_file($this->app->basePath(self::LOCK_FILE))) {
            throw new HttpException(404);
        }

        $key = $this->app->config()->get('app.key');

        if (! is_string($key) || $key === '') {
            return $this->page('Falta la APP_KEY', '<p>Define <code>APP_KEY</code> en el archivo <code>.env</code> del servidor y recarga esta página.</p>', 503);
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form();
        }

        $throttle = 'installer|' . $request->getClientIp();

        if ($this->limiter->tooManyAttempts($throttle, self::MAX_ATTEMPTS)) {
            return $this->form('Demasiados intentos. Espera ' . (int) ceil($this->limiter->availableIn($throttle) / 60) . ' minutos.', 429);
        }

        $given = $request->getBody('key');

        if (! is_string($given) || ! hash_equals($key, $given)) {
            $this->limiter->hit($throttle, 600);

            return $this->form('La clave no coincide con la APP_KEY del archivo .env.', 403);
        }

        $this->limiter->clear($throttle);

        try {
            $migrator = $this->app->make(Migrator::class);
            $ran = $migrator instanceof Migrator ? $migrator->run() : [];
        } catch (Throwable $e) {
            logger('Falló la instalación web: ' . $e->getMessage(), 'error', ['exception' => $e]);

            return $this->page('No se pudo instalar', '<p>' . e($e->getMessage()) . '</p><p>Revisa la conexión a la base de datos en <code>.env</code> y vuelve a intentarlo.</p>', 500);
        }

        $list = $ran === []
            ? '<p>No había migraciones pendientes.</p>'
            : '<ul>' . implode('', array_map(static fn (string $name): string => '<li>' . e($name) . '</li>', $ran)) . '</ul>';

        if (! $this->lock($ran)) {
            return $this->page(
                'Migraciones listas, pero la página sigue abierta',
                $list . '<p><strong>No se pudo escribir <code>' . self::LOCK_FILE . '</code>.</strong> '
                . 'Pon <code>INSTALL_ENABLED=false</code> en <code>.env</code> ya y revisa los permisos de <code>storage/</code>.</p>',
                500
            );
        }

        return $this->page(
            'Instalación lista',
            $list . '<p><strong>Ahora pon <code>INSTALL_ENABLED=false</code> en <code>.env</code>.</strong> '
            . 'Esta página ya quedó bloqueada (<code>' . self::LOCK_FILE . '</code>); para volver a usarla, borra ese archivo.</p>'
        );
    }

    /**
     * @param list<string> $ran
     */
    private function lock(array $ran): bool
    {
        $file = $this->app->basePath(self::LOCK_FILE);
        $directory = dirname($file);

        if (! is_dir($directory) && (file_exists($directory) || ! mkdir($directory, 0775, true))) {
            return false;
        }

        return is_writable($directory) && file_put_contents($file, 'Instalado el ' . date('c') . "\n" . implode("\n", $ran) . "\n") !== false;
    }

    private function form(string $error = '', int $status = 200): Response
    {
        $message = $error === '' ? '' : '<p class="error">' . e($error) . '</p>';

        return $this->page(
            'Instalar ' . e((string) $this->app->config()->get('app.name', 'la aplicación')),
            '<p>Ejecuta las migraciones de la base de datos. Escribe la <code>APP_KEY</code> del archivo <code>.env</code> del servidor.</p>'
            . $message
            . '<form method="POST"><label>APP_KEY <input type="password" name="key" autocomplete="off" required autofocus></label>'
            . '<button type="submit">Instalar</button></form>',
            $status
        );
    }

    private function page(string $title, string $body, int $status = 200): Response
    {
        return Response::html(
            '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>' . $title . '</title><style>'
            . 'body{font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;color:#111827}'
            . 'input{display:block;width:100%;margin:.5rem 0 1rem;padding:.5rem;border:1px solid #d1d5db;border-radius:.5rem;box-sizing:border-box}'
            . 'button{padding:.5rem 1rem;border:0;border-radius:.5rem;background:#2563eb;color:#fff;font-weight:600}'
            . '.error{color:#b91c1c}code{background:#f3f4f6;padding:0 .25rem;border-radius:.25rem}'
            . '</style></head><body><h1>' . $title . '</h1>' . $body . '</body></html>',
            $status,
            ['Cache-Control' => 'no-store']
        );
    }
}
