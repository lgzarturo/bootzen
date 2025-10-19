<?php

/**
 * Cache.php
 *
 * Clase Cache del framework BootZen.
 *
 * Gestiona el almacenamiento en caché utilizando Redis, permitiendo operaciones eficientes de lectura,
 * escritura, borrado y estadísticas. Soporta multi-tenant mediante prefijos de clave y provee métodos para
 * operaciones atómicas y manejo de múltiples valores.
 *
 * Implementa el patrón Singleton para la conexión Redis y el patrón Decorator para la serialización de datos.
 *
 * Consideraciones de seguridad: serializa los datos para evitar inyecciones y valida la conexión antes de operar.
 * Consideraciones de rendimiento: utiliza operaciones en lote (pipeline) y TTL para optimizar el uso de memoria
 * y la velocidad de acceso.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.9
 * @since      1.0.0
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

use Closure;
use Predis\Client as PredisClient;
use Predis\Connection\ConnectionException;

/**
 * Clase principal para la gestión de caché en BootZen.
 *
 */
class Cache
{
    /**
     * Instancia de Predis para la conexión con Redis.
     *
     * @var PredisClient
     */
    private PredisClient $redis;

    /**
     * Prefijo global para las claves de caché.
     *
     * @var string
     */
    private string $prefix;

    /**
     * Identificador del inquilino para soporte multi-tenant.
     *
     * @var string|null
     */
    private ?string $tenantId = null;

    /**
     * Establece el identificador de inquilino para la construcción de claves de caché.
     *
     * @param string|null $tenantId Identificador único del inquilino o null para modo global
     * @return void
     * @example $cache->setTenantId('empresa123');
     */
    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Constructor de la clase Cache.
     *
     * @param PredisClient|null $redis Instancia de Predis opcional (para pruebas o inyección de dependencias)
     * @param string $prefix Prefijo global para las claves de caché
     * @throws \RuntimeException Si la conexión a Redis falla
     * @example $cache = new Cache();
     */
    public function __construct(?PredisClient $redis = null, string $prefix = 'bootzen')
    {
        $this->redis = $redis ?? $this->createRedisConnection();
        $this->prefix = $prefix;
    }

    /**
     * Crea una conexión a Redis utilizando Predis.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @throws \RuntimeException Si la conexión a Redis falla
     * @return PredisClient Instancia de cliente Predis conectada
     */
    private function createRedisConnection(): PredisClient
    {
        $config = [
            'scheme' => 'tcp',
            'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            'database' => (int) ($_ENV['REDIS_DB'] ?? 0),
        ];

        if ($password = $_ENV['REDIS_PASSWORD'] ?? null) {
            $config['password'] = $password;
        }

        try {
            $redis = new PredisClient($config);
            $redis->connect();

            return $redis;
        } catch (ConnectionException $e) {
            throw new \RuntimeException("Fallo al conectar con Redis: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene un valor de la caché.
     *
     * @param string $key Clave de caché
     * @param mixed $default Valor por defecto si la clave no existe
     * @return mixed Valor almacenado o $default si no existe
     * @throws \Exception Si ocurre un error de conexión
     * @example $valor = $cache->get('usuario:1');
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = $this->redis->get($this->buildKey($key));
            if ($value === null) {
                return $default;
            }

            return $this->unserialize($value);
        } catch (\Exception $e) {
            error_log("Cache get error: " . $e->getMessage());

            return $default;
        }
    }

    /**
     * Almacena un valor en la caché.
     *
     * @param string $key Clave de caché
     * @param mixed $value Valor a almacenar
     * @param int $ttl Tiempo de vida en segundos (por defecto 3600)
     * @return bool true si la operación fue exitosa
     * @throws \Exception Si ocurre un error de conexión
     * @example $cache->set('usuario:1', $usuario, 600);
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        try {
            $serialized = $this->serialize($value);
            if ($ttl > 0) {
                return $this->redis->setex($this->buildKey($key), $ttl, $serialized) == 'OK';
            }

            return $this->redis->set($this->buildKey($key), $serialized) == 'OK';
        } catch (\Exception $e) {
            error_log("Cache set error: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Elimina un valor de la caché.
     *
     * @param string $key Clave de caché
     * @return bool true si la clave fue eliminada
     * @throws \Exception Si ocurre un error de conexión
     * @example $cache->delete('usuario:1');
     */
    public function delete(string $key): bool
    {
        try {
            return $this->redis->del($this->buildKey($key)) > 0;
        } catch (\Exception $e) {
            error_log("Cache delete error: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Verifica si una clave existe en la caché.
     *
     * @param string $key Clave de caché
     * @return bool true si la clave existe
     * @throws \Exception Si ocurre un error de conexión
     * @example if ($cache->has('usuario:1')) { ... }
     */
    public function has(string $key): bool
    {
        try {
            return $this->redis->exists($this->buildKey($key)) > 0;
        } catch (\Exception $e) {
            error_log("Cache exists error: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Obtiene múltiples valores de la caché.
     *
     * @param array<int, string> $keys Lista de claves
     * @param mixed $default Valor por defecto si alguna clave no existe
     * @return array<string, mixed> Array asociativo clave => valor
     * @throws \Exception Si ocurre un error de conexión
     * @example $cache->getMultiple(['user:1', 'user:2']);
     */
    public function getMultiple(array $keys, mixed $default = null): array
    {
        try {
            $cacheKeys = array_map([$this, 'buildKey'], $keys);
            $values = $this->redis->mget($cacheKeys);
            $result = [];
            foreach ($keys as $index => $key) {
                $value = $values[$index];
                $result[$key] = $value !== false ? $this->unserialize($value) : $default;
            }

            return $result;
        } catch (\Exception $e) {
            error_log("Cache getMultiple error: " . $e->getMessage());

            return array_fill_keys($keys, $default);
        }
    }

    /**
     * Almacena múltiples valores en la caché.
     *
     * @param array<string, mixed> $values Array asociativo clave => valor
     * @param int $ttl Tiempo de vida en segundos
     * @return bool true si todas las operaciones fueron exitosas
     * @throws \Exception Si ocurre un error de conexión
     * @example $cache->setMultiple(['a'=>1, 'b'=>2], 120);
     */
    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        try {
            $cacheValues = [];
            foreach ($values as $key => $value) {
                $cacheValues[$this->buildKey($key)] = $this->serialize($value);
            }
            if ($ttl > 0) {
                $pipe = $this->redis->multi();
                foreach ($cacheValues as $key => $value) {
                    $pipe->setex($key, $ttl, $value);
                }
                $results = $pipe->exec();

                return ! in_array(false, $results, true);
            }

            return $this->redis->mset($cacheValues);
        } catch (\Exception $e) {
            error_log("Cache setMultiple error: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Obtiene un valor de la caché o ejecuta el callback y almacena el resultado.
     *
     * @param string $key Clave de caché
     * @param Closure $callback Función a ejecutar si la clave no existe
     * @param int $ttl Tiempo de vida en segundos
     * @return mixed Valor almacenado o resultado del callback
     * @example
     * $user = $cache->remember('user:1', function() {
     *     return UserRepository::find(1);
     * }, 300);
     */
    public function remember(string $key, Closure $callback, int $ttl = 3600): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Incrementa un valor numérico en la caché.
     *
     * @param string $key Clave de caché
     * @param int $value Valor a incrementar (por defecto 1)
     * @return int|false Nuevo valor tras el incremento o false en error
     * @throws \Exception Si ocurre un error de conexión
     * @example $cache->increment('contador');
     */
    public function increment(string $key, int $value = 1): int|false
    {
        try {
            return $this->redis->incrby($this->buildKey($key), $value);
        } catch (\Exception $e) {
            error_log("Cache increment error: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Decrementa un valor numérico en la caché.
     *
     * @param string $key Clave de caché
     * @param int $value Valor a decrementar (por defecto 1)
     * @return int|false Nuevo valor tras el decremento o false en error
     * @throws \Exception Si ocurre un error de conexión
     * @example $cache->decrement('contador');
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        try {
            return $this->redis->decrby($this->buildKey($key), $value);
        } catch (\Exception $e) {
            error_log("Cache decrement error: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Elimina todas las claves de caché que coincidan con un patrón.
     *
     * @param string $pattern Patrón de búsqueda (por defecto '*')
     * @return bool true si la operación fue exitosa
     * @throws \Exception Si ocurre un error de conexión
     * @example $cache->flush('user:*');
     */
    public function flush(string $pattern = '*'): bool
    {
        try {
            $keys = $this->redis->keys($this->buildKey($pattern));
            if (empty($keys)) {
                return true;
            }

            return $this->redis->del($keys) > 0;
        } catch (\Exception $e) {
            error_log("Cache flush error: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Obtiene estadísticas de uso de la caché.
     *
     * @return array<string, int|float|string> Array con hits, misses, hit_ratio, memory_used, connected_clients
     * @throws \Exception Si ocurre un error de conexión
     * @example $stats = $cache->stats();
     */
    public function stats(): array
    {
        try {
            $info = $this->redis->info();

            return [
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'hit_ratio' => $this->calculateHitRatio($info),
                'memory_used' => $info['used_memory_human'] ?? '0B',
                'connected_clients' => $info['connected_clients'] ?? 0,
            ];
        } catch (\Exception $e) {
            error_log("Cache stats error: " . $e->getMessage());

            return [];
        }
    }

    /**
     * Construye la clave de caché con el prefijo y el identificador de inquilino.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param string $key Clave base
     * @return string Clave completa para Redis
     */
    private function buildKey(string $key): string
    {
        $parts = [$this->prefix];
        if ($this->tenantId) {
            $parts[] = 'tenant';
            $parts[] = $this->tenantId;
        }
        $parts[] = $key;

        return implode(':', $parts);
    }

    /**
     * Serializa el valor para su almacenamiento seguro en Redis.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param mixed $value Valor a serializar
     * @return string Valor serializado
     */
    private function serialize(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return serialize($value);
    }

    /**
     * Deserializa el valor recuperado de Redis.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param string $value Valor serializado
     * @return mixed Valor deserializado o string si no es serializable
     */
    private function unserialize(string $value): mixed
    {
        // Intenta deserializar, si falla retorna como string
        $unserialized = @unserialize($value);

        return $unserialized !== false ? $unserialized : $value;
    }

    /**
     * Calcula el ratio de aciertos de la caché.
     *
     * @internal Solo para uso interno del framework BootZen.
     * @param array<string, mixed> $info Salida del comando INFO de Redis
     * @return float Porcentaje de aciertos
     */
    private function calculateHitRatio(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * @todo Implementar soporte para etiquetas de caché en futuras versiones.
     */
}
