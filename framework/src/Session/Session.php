<?php

/**
 * Session.php
 *
 * Sesión de la petición actual: datos, mensajes flash, input anterior, errores de validación y token CSRF.
 * La crea el middleware StartSession, que la guarda al final de la petición.
 *
 * @package    BootZen\Session
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Session;

/**
 * Datos de sesión. Guarda solo escalares y arrays (los objetos no se restauran).
 *
 * @example session()->put('carrito', [3, 7]);
 * @example session()->flash('status', 'Tarea creada');
 */
final class Session
{
    private const FLASH_NEW = '_flash.new';
    private const FLASH_OLD = '_flash.old';

    /**
     * @param array<string, mixed> $attributes
     * @param bool $exists Si la sesión ya estaba guardada en el almacén
     */
    public function __construct(
        private readonly SessionStore $store,
        private string $id,
        private array $attributes = [],
        private bool $exists = false
    ) {
    }

    /**
     * Genera un id nuevo: 40 caracteres hexadecimales aleatorios.
     */
    public static function generateId(): string
    {
        return bin2hex(random_bytes(20));
    }

    public static function isValidId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{40}$/', $id) === 1;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    /**
     * @param string|array<string, mixed> $key Clave o array clave => valor
     */
    public function put(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $name => $item) {
            $this->attributes[$name] = $item;
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes) && $this->attributes[$key] !== null;
    }

    public function forget(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Devuelve el valor y lo borra de la sesión.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Guarda un valor que solo estará disponible en la siguiente petición.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->put($key, $value);
        $this->put(self::FLASH_NEW, array_values(array_unique([...$this->flashKeys(self::FLASH_NEW), $key])));
        $this->put(self::FLASH_OLD, array_values(array_diff($this->flashKeys(self::FLASH_OLD), [$key])));
    }

    /**
     * Borra los datos flash de la petición anterior y deja los de esta para la siguiente.
     *
     * @internal Lo llama StartSession al final de cada petición
     */
    public function ageFlashData(): void
    {
        foreach ($this->flashKeys(self::FLASH_OLD) as $key) {
            $this->forget($key);
        }

        $this->put(self::FLASH_OLD, $this->flashKeys(self::FLASH_NEW));
        $this->put(self::FLASH_NEW, []);
    }

    /**
     * Valor anterior de un campo de formulario (tras un error de validación).
     */
    public function getOldInput(string $key, mixed $default = null): mixed
    {
        $value = $this->get('_old_input', []);

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Errores de validación de la petición anterior: campo => mensajes.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $errors = $this->get('_errors', []);

        /** @var array<string, list<string>> $result */
        $result = is_array($errors) ? $errors : [];

        return $result;
    }

    /**
     * Token CSRF de la sesión. Se genera la primera vez que se pide.
     */
    public function token(): string
    {
        $token = $this->get('_token');

        if (! is_string($token) || $token === '') {
            $token = $this->regenerateToken();
        }

        return $token;
    }

    public function regenerateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->put('_token', $token);

        return $token;
    }

    /**
     * Cambia el id de la sesión conservando los datos. Se usa al iniciar sesión para evitar fijación de sesión.
     */
    public function regenerate(): void
    {
        if ($this->exists) {
            $this->store->destroy($this->id);
            $this->exists = false;
        }

        $this->id = self::generateId();
    }

    /**
     * Borra todos los datos, cambia el id y genera un token CSRF nuevo (cerrar sesión).
     */
    public function invalidate(): void
    {
        $this->attributes = [];
        $this->regenerate();
        $this->regenerateToken();
    }

    /**
     * URL de la última página vista (GET), para volver tras un error de validación.
     */
    public function previousUrl(): ?string
    {
        $url = $this->get('_previous_url');

        return is_string($url) ? $url : null;
    }

    /**
     * Indica si hay algo que guardar: una sesión existente o una nueva con datos.
     * Así las peticiones que no usan la sesión (health checks, bots) no crean archivos ni cookies.
     */
    public function shouldPersist(): bool
    {
        if ($this->exists) {
            return true;
        }

        foreach ($this->attributes as $key => $value) {
            if (! in_array($key, [self::FLASH_NEW, self::FLASH_OLD], true) || $value !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guarda la sesión en el almacén.
     */
    public function save(): void
    {
        $this->store->write($this->id, $this->attributes);
        $this->exists = true;
    }

    /**
     * @return list<string>
     */
    private function flashKeys(string $bucket): array
    {
        $keys = $this->get($bucket, []);

        return is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
    }
}
