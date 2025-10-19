<?php

/**
 * CacheTenant.php
 *
 * Envoltorio especializado para caché multi-inquilino en el framework BootZen.
 * Permite gestionar el almacenamiento en caché de datos asociados a un inquilino específico,
 * garantizando el aislamiento de datos y la reutilización de la lógica de caché base.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.1.0
 * @since      1.0.0
 *
 * @see        BootZen\Core\Cache
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

use Closure;
use Predis\Client as PredisClient;

/**
 * Clase CacheTenant
 *
 * Proporciona una interfaz de caché aislada por inquilino (tenant) reutilizando la lógica de la clase Cache.
 * Facilita la gestión de datos multi-tenant en aplicaciones SaaS,
 * asegurando que cada inquilino acceda únicamente a su propio espacio de caché.
 *
 * Relación: Composición sobre la clase Cache.
 *
 */
class CacheTenant
{
    /**
     * Instancia de la caché base configurada para el inquilino.
     *
     * @var Cache
     */
    private Cache $cache;

    /**
     * Constructor de CacheTenant.
     *
     * @param PredisClient $redis Instancia de cliente Redis
     * @param string $prefix Prefijo global para las claves de caché
     * @param string $tenantId Identificador único del inquilino
     * @example $tenantCache = new CacheTenant($redis, 'bootzen', 'empresa123');
     */
    public function __construct(PredisClient $redis, string $prefix, string $tenantId)
    {
        $this->cache = new Cache($redis, $prefix);
        $this->cache->setTenantId($tenantId);
    }

    /**
     * Obtiene un valor de la caché del inquilino.
     *
     * @param string $key Clave de caché
     * @param mixed $default Valor por defecto si la clave no existe
     * @return mixed Valor almacenado o $default si no existe
     * @example $tenantCache->get('usuario:1');
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * Almacena un valor en la caché del inquilino.
     *
     * @param string $key Clave de caché
     * @param mixed $value Valor a almacenar
     * @param int $ttl Tiempo de vida en segundos (por defecto 3600)
     * @return bool true si la operación fue exitosa
     * @example $tenantCache->set('usuario:1', $usuario, 600);
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * Elimina un valor de la caché del inquilino.
     *
     * @param string $key Clave de caché
     * @return bool true si la clave fue eliminada
     * @example $tenantCache->delete('usuario:1');
     */
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    /**
     * Verifica si una clave existe en la caché del inquilino.
     *
     * @param string $key Clave de caché
     * @return bool true si la clave existe
     * @example if ($tenantCache->has('usuario:1')) { ... }
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * Obtiene un valor de la caché o ejecuta el callback y almacena el resultado para el inquilino.
     *
     * @param string $key Clave de caché
     * @param Closure $callback Función a ejecutar si la clave no existe
     * @param int $ttl Tiempo de vida en segundos
     * @return mixed Valor almacenado o resultado del callback
     * @example
     * $user = $tenantCache->remember('user:1', function() {
     *     return UserRepository::find(1);
     * }, 300);
     */
    public function remember(string $key, Closure $callback, int $ttl = 3600): mixed
    {
        return $this->cache->remember($key, $callback, $ttl);
    }

    /**
     * Elimina todas las claves de caché del inquilino que coincidan con un patrón.
     *
     * @param string $pattern Patrón de búsqueda (por defecto '*')
     * @return bool true si la operación fue exitosa
     * @example $tenantCache->flush('user:*');
     */
    public function flush(string $pattern = '*'): bool
    {
        return $this->cache->flush($pattern);
    }
}
