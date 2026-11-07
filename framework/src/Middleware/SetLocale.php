<?php

/**
 * SetLocale.php
 *
 * Middleware 'locale': fija el idioma de la petición. Con parámetro ('locale:en') lo fuerza; así lo
 * usan las rutas con prefijo de $router->localized(). Sin parámetro, para apps sin prefijo en la URL,
 * usa ?lang=xx (y lo recuerda en la sesión), el idioma guardado en la sesión, el del navegador
 * (Accept-Language) o APP_LOCALE, en ese orden.
 *
 * @package    BootZen\Middleware
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.4.0
 */

declare(strict_types=1);

namespace BootZen\Middleware;

use BootZen\Core\ParameterizedMiddleware;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Translation\Translator;
use InvalidArgumentException;

/**
 * Solo acepta idiomas de APP_LOCALES. Agrega Content-Language a la respuesta.
 *
 * @example $app->middlewareGroup('web', [StartSession::class, VerifyCsrfToken::class, 'locale']);
 */
final class SetLocale implements ParameterizedMiddleware
{
    private ?string $forced = null;

    public function __construct(private readonly Translator $translator)
    {
    }

    public function withParameters(array $parameters): static
    {
        $locale = $parameters[0] ?? '';

        if (! $this->translator->isSupported($locale)) {
            throw new InvalidArgumentException("El idioma [{$locale}] de 'locale:{$locale}' no está en APP_LOCALES.");
        }

        $copy = clone $this;
        $copy->forced = $locale;

        return $copy;
    }

    public function process(Request $request, callable $next): Response
    {
        $locale = $this->forced ?? $this->detect($request);
        $this->translator->setLocale($locale);
        $this->translator->setCurrentRoute($request->getRoute(), $request->params(), $request->getPath());

        $response = $next($request);

        return $response instanceof Response ? $response->withHeader('Content-Language', $locale) : $response;
    }

    private function detect(Request $request): string
    {
        $session = $request->hasSession() ? $request->session() : null;
        $chosen = $request->getQuery('lang');

        if (is_string($chosen) && $this->translator->isSupported($chosen)) {
            $session?->put('locale', $chosen);

            return $chosen;
        }

        $stored = $session?->get('locale');

        if (is_string($stored) && $this->translator->isSupported($stored)) {
            return $stored;
        }

        return $this->fromHeader((string) $request->getHeader('accept-language')) ?? $this->translator->defaultLocale();
    }

    /**
     * El primer idioma permitido del Accept-Language, por peso (q=) y en orden.
     */
    private function fromHeader(string $header): ?string
    {
        $candidates = [];

        foreach (explode(',', $header) as $index => $part) {
            [$tag, $weight] = array_pad(explode(';q=', trim($part)), 2, '1');
            $tag = strtolower(trim($tag));

            if ($tag !== '' && $tag !== '*') {
                $candidates[] = [$tag, (float) $weight, $index];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        foreach ($candidates as [$tag]) {
            foreach ([$tag, explode('-', $tag)[0]] as $option) {
                if ($this->translator->isSupported($option)) {
                    return $option;
                }
            }
        }

        return null;
    }
}
