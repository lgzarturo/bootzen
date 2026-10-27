<?php

/**
 * StartSession.php
 *
 * Abre la sesión a partir de la cookie, la deja disponible en la petición y en session(), guarda los datos
 * flash de la respuesta y escribe la cookie. También convierte los errores de validación de formularios
 * en una redirección a la página anterior con los errores y el input.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Core\Config;
use BootZen\Core\Container;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Session\Session;
use BootZen\Session\SessionStore;
use BootZen\Validation\ValidationException;
use BootZen\View\View;

/**
 * Middleware de sesión (grupo web). Configuración en config/session.php.
 */
final class StartSession implements MiddlewareInterface
{
    /**
     * Campos que nunca se guardan para old().
     */
    private const NOT_FLASHED = ['password', 'password_confirmation', 'current_password', '_token', '_method'];

    public function __construct(
        private readonly SessionStore $store,
        private readonly Container $container,
        private readonly Config $config
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $session = $this->start($request);
        $this->container->instance(Session::class, $session);

        if ($this->container->bound(View::class)) {
            $view = $this->container->make(View::class);
            if ($view instanceof View) {
                $view->share('errors', $session->errors());
            }
        }

        try {
            $response = $next($request->withSession($session));
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                throw $e;
            }

            $response = Response::redirect($session->previousUrl() ?? $request->getHeader('referer') ?? '/')
                ->withErrors($e->errors())
                ->withInput($request->except(self::NOT_FLASHED));
        }

        // Primero lo nuevo y luego el envejecimiento: lo de esta respuesta queda para la siguiente petición
        foreach ($response->getFlash() as $key => $value) {
            $session->flash($key, $value);
        }
        $session->ageFlashData();

        if (! $session->shouldPersist()) {
            return $response;
        }

        if ($request->getMethod() === 'GET' && ! $request->isAjax() && ! $request->wantsJson() && $response->isSuccessful()) {
            $session->put('_previous_url', $request->getUri());
        }

        $session->save();
        $this->collectGarbage();

        return $response->withCookie(
            $this->cookieName(),
            $session->getId(),
            time() + $this->lifetime(),
            (string) $this->setting('path', '/'),
            (string) $this->setting('domain', ''),
            is_bool($this->setting('secure')) ? (bool) $this->setting('secure') : $request->isSecure(),
            true,
            (string) $this->setting('same_site', 'Lax')
        );
    }

    /**
     * Retoma la sesión de la cookie si existe en el almacén; si no, crea una con id nuevo.
     * Un id desconocido nunca se reutiliza, así nadie puede fijar el id de otra persona.
     */
    private function start(Request $request): Session
    {
        $id = $request->getCookie($this->cookieName());

        if (is_string($id) && Session::isValidId($id)) {
            $data = $this->store->read($id);

            if ($data !== null) {
                return new Session($this->store, $id, $data, true);
            }
        }

        return new Session($this->store, Session::generateId());
    }

    /**
     * Limpia sesiones viejas en el 2% de las peticiones.
     */
    private function collectGarbage(): void
    {
        if (random_int(1, 100) <= 2) {
            $this->store->gc($this->lifetime());
        }
    }

    private function cookieName(): string
    {
        return (string) $this->setting('cookie', 'bootzen_session');
    }

    /**
     * Duración en segundos (config session.lifetime está en minutos).
     */
    private function lifetime(): int
    {
        $minutes = $this->setting('lifetime', 120);

        return (is_numeric($minutes) ? (int) $minutes : 120) * 60;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return $this->config->get('session.' . $key, $default);
    }
}
