<?php

/**
 * SignedUrl.php
 *
 * Enlaces firmados con la APP_KEY para rutas con nombre: verificación de correo, darse de baja,
 * descargar una factura sin iniciar sesión. Vencen solos.
 *
 * @package    BootZen\Security
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.6.0
 */

declare(strict_types=1);

namespace BootZen\Security;

use BootZen\Core\Request;
use SensitiveParameter;

/**
 * La firma cubre el nombre de la ruta (sin prefijo de idioma) y todos los parámetros, no el dominio
 * ni la carpeta: el enlace sigue siendo válido detrás de un proxy, en una subcarpeta o en /en/.
 *
 * @example signed_route('facturas.descargar', ['id' => 7], 60 * 24); // vence en un día
 * @example $router->get('/facturas/{id}/descargar', ...)->name('facturas.descargar')->middleware('signed');
 */
final class SignedUrl
{
    public function __construct(#[SensitiveParameter] private readonly string $key)
    {
    }

    /**
     * Parámetros con expires y signature agregados.
     *
     * @param array<string, scalar> $parameters Parámetros de la ruta y del query string
     * @return array<string, string>
     */
    public function sign(string $route, array $parameters, int $expires): array
    {
        $values = array_map(static fn (mixed $value): string => is_bool($value) ? ($value ? '1' : '0') : (string) $value, $parameters);
        $values['expires'] = (string) $expires;
        $values['signature'] = $this->signature($route, $values);

        return $values;
    }

    /**
     * Comprueba la firma y el vencimiento de la petición actual (ruta, parámetros y query string).
     */
    public function check(Request $request, int $now): bool
    {
        $name = $request->getRoute()?->getName();
        $query = $request->getQuery();
        $values = array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
            array_merge(is_array($query) ? $query : [], $request->params())
        );
        $signature = $values['signature'] ?? '';
        unset($values['signature']);
        $expires = $values['expires'] ?? '';

        return $name !== null && ctype_digit($expires) && (int) $expires >= $now
            && hash_equals($this->signature($name, $values), $signature);
    }

    /**
     * @param array<string, string> $values
     */
    private function signature(string $route, array $values): string
    {
        unset($values['signature']);
        ksort($values);
        // 'en::verification.verify' y 'verification.verify' firman igual: el enlace vale en cualquier idioma
        $base = str_contains($route, '::') ? substr($route, (int) strpos($route, '::') + 2) : $route;

        return hash_hmac('sha256', $base . '?' . http_build_query($values), 'bootzen-url|' . $this->key);
    }
}
