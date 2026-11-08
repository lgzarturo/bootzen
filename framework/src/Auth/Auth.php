<?php

/**
 * Auth.php
 *
 * Autenticación con sesión: login, logout y usuario actual. El modelo de usuario se configura en
 * config/auth.php (por defecto App\Models\User, con columnas email y password).
 *
 * @package    BootZen\Auth
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Auth;

use BootZen\Database\Model;
use BootZen\Session\Session;
use Closure;
use LogicException;

/**
 * Guard de sesión. Se obtiene con auth() en rutas que pasan por el grupo web.
 *
 * @example if (auth()->attempt($request->only(['email', 'password']))) { ... }
 * @example auth()->user()?->nombre
 */
final class Auth
{
    private const SESSION_KEY = '_auth_id';

    /**
     * Huella del hash de la contraseña: si la contraseña cambia, las demás sesiones se cierran solas.
     */
    private const HASH_KEY = '_auth_hash';

    /**
     * Hash de relleno para verificar cuando el usuario no existe y tardar lo mismo.
     */
    private static ?string $dummyHash = null;

    /**
     * Usuario cargado y la sesión para la que se cargó.
     */
    private ?Model $user = null;

    private ?Session $loadedFor = null;

    /**
     * Borra los "recordarme" del usuario en todos sus dispositivos. Úsalo al restablecer la contraseña:
     * las sesiones abiertas ya se cierran solas porque cambia la huella, pero una cookie de recordarme
     * volvería a entrar.
     *
     * @example auth()->forgetRememberedDevices($user);
     */
    public function forgetRememberedDevices(Model $user): void
    {
        $key = $user->getKey();

        if ($key !== null && $this->rememberTokens !== null) {
            $this->tokens()->forgetUser($key);
        }
    }

    /**
     * Cookie de "recordarme" pendiente para la respuesta: null nada, '' borrarla, texto ponerla.
     */
    private ?string $queuedCookie = null;

    /**
     * @param Closure(): ?Session $session Sesión de la petición actual (null fuera del grupo web)
     * @param string $model Clase del modelo de usuario
     * @param (Closure(): RememberTokens)|null $rememberTokens Tokens de "recordarme" (tabla remember_tokens)
     */
    public function __construct(
        private readonly Closure $session,
        private readonly string $model,
        private readonly ?Closure $rememberTokens = null
    ) {
    }

    /**
     * Usuario autenticado, o null si es un invitado (o si la petición no tiene sesión, como un 404).
     */
    public function user(): ?Model
    {
        $session = ($this->session)();

        if ($session === null) {
            return null;
        }

        if ($this->loadedFor !== $session) {
            $id = $session->get(self::SESSION_KEY);
            $user = is_int($id) || is_string($id) ? $this->model()::find($id) : null;
            $stored = $session->get(self::HASH_KEY);
            $current = $user === null ? null : self::fingerprint($user);

            if ($user !== null && is_string($stored) && $current !== null && ! hash_equals($stored, $current)) {
                // La contraseña cambió desde que esta sesión inició: se cierra
                $session->forget(self::SESSION_KEY);
                $session->forget(self::HASH_KEY);
                $user = null;
            } elseif ($user !== null && ! is_string($stored) && $current !== null) {
                // Sesiones anteriores a la huella: se la pone sin cerrarlas
                $session->put(self::HASH_KEY, $current);
            }

            $this->user = $user;
            $this->loadedFor = $session;
        }

        return $this->user;
    }

    /**
     * Id del usuario autenticado, sin consultar la base de datos.
     */
    public function id(): int|string|null
    {
        $id = ($this->session)()?->get(self::SESSION_KEY);

        return is_int($id) || is_string($id) ? $id : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * Busca al usuario por las credenciales (todas menos password) y verifica la contraseña.
     * Si coincide, inicia sesión. Si el hash está desactualizado, lo rehace.
     *
     * @param array<string, mixed> $credentials Por ejemplo ['email' => ..., 'password' => ...]
     * @param bool $remember Mantener la sesión en este dispositivo aunque se cierre el navegador
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $user = $this->validate($credentials);

        if ($user === null) {
            return false;
        }

        $this->login($user, $remember);

        return true;
    }

    /**
     * Devuelve el usuario si las credenciales son correctas, sin iniciar sesión.
     *
     * @param array<string, mixed> $credentials
     */
    public function validate(array $credentials): ?Model
    {
        $password = $credentials['password'] ?? null;
        unset($credentials['password']);

        if (! is_string($password) || $credentials === []) {
            return null;
        }

        $query = $this->model()::query();
        foreach ($credentials as $column => $value) {
            $query->where($column, $value);
        }

        $user = $query->first();
        $hash = $user?->getAttribute('password');

        if ($user === null || ! is_string($hash)) {
            // Se verifica igual contra un hash cualquiera para no revelar por tiempo si el usuario existe
            Hash::check($password, self::$dummyHash ??= Hash::make(bin2hex(random_bytes(8))));

            return null;
        }

        if (! Hash::check($password, $hash)) {
            return null;
        }

        if (Hash::needsRehash($hash)) {
            $user->setAttribute('password', Hash::make($password));
            $user->save();
        }

        return $user;
    }

    /**
     * Inicia sesión como el usuario. Cambia el id de la sesión y el token CSRF para evitar fijación de
     * sesión. Con $remember deja una cookie que vuelve a iniciar la sesión al regresar (necesita la
     * tabla remember_tokens y el middleware RememberMe en el grupo web).
     */
    public function login(Model $user, bool $remember = false): void
    {
        $session = $this->session();
        $session->regenerate();
        $session->regenerateToken();
        $session->put(self::SESSION_KEY, $user->getKey());

        $fingerprint = self::fingerprint($user);
        if ($fingerprint !== null) {
            $session->put(self::HASH_KEY, $fingerprint);
        }

        $key = $user->getKey();
        if ($remember && $key !== null) {
            $this->queuedCookie = $this->tokens()->issue($key);
        }

        $this->user = $user;
        $this->loadedFor = $session;
    }

    /**
     * Inicia sesión con el id del usuario; null si no existe.
     */
    public function loginUsingId(int|string $id, bool $remember = false): ?Model
    {
        $user = $this->model()::find($id);

        if ($user !== null) {
            $this->login($user, $remember);
        }

        return $user;
    }

    /**
     * Cierra la sesión: borra todos sus datos, cambia el id y olvida la cookie de "recordarme".
     */
    public function logout(): void
    {
        $session = $this->session();
        $session->invalidate();

        $this->queuedCookie = '';
        $this->user = null;
        $this->loadedFor = $session;
    }

    /**
     * Cierra la sesión en los demás dispositivos: pide la contraseña actual, la vuelve a cifrar (las otras
     * sesiones ven otra huella y se cierran) y borra todos los "recordarme". Esta sesión sigue abierta.
     *
     * @return bool false si la contraseña no es correcta
     * @example if (! auth()->logoutOtherDevices($request->input('password'))) { throw ValidationException::withMessages([...]); }
     */
    public function logoutOtherDevices(string $password): bool
    {
        $user = $this->user();
        $hash = $user?->getAttribute('password');
        $key = $user?->getKey();

        if ($user === null || $key === null || ! is_string($hash) || ! Hash::check($password, $hash)) {
            return false;
        }

        $user->setAttribute('password', Hash::make($password));
        $user->save();

        $this->session()->put(self::HASH_KEY, (string) self::fingerprint($user));

        if ($this->rememberTokens !== null) {
            $this->tokens()->forgetUser($key);
        }

        return true;
    }

    /**
     * Cookie de "recordarme" pendiente para la respuesta (null nada, '' borrarla) y la limpia.
     *
     * @internal Lo usa el middleware RememberMe.
     */
    public function pullQueuedCookie(): ?string
    {
        $cookie = $this->queuedCookie;
        $this->queuedCookie = null;

        return $cookie;
    }

    /**
     * @internal Lo usa el middleware RememberMe.
     * @throws LogicException Si no hay tokens configurados
     */
    public function tokens(): RememberTokens
    {
        if ($this->rememberTokens === null) {
            throw new LogicException('"Recordarme" necesita la tabla remember_tokens: corre las migraciones del scaffold --auth.');
        }

        return ($this->rememberTokens)();
    }

    /**
     * URL que el usuario intentaba visitar antes de que lo mandaran al login (la guarda el middleware auth).
     */
    public function intended(string $default = '/'): string
    {
        $url = $this->session()->pull('_intended');

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : $default;
    }

    /**
     * @throws LogicException Si la petición no tiene sesión
     */
    private function session(): Session
    {
        return ($this->session)() ?? throw new LogicException(
            'auth() necesita sesión para iniciar o cerrar sesión: usa la ruta dentro del grupo web (StartSession).'
        );
    }

    private static function fingerprint(Model $user): ?string
    {
        $hash = $user->getAttribute('password');

        return is_string($hash) && $hash !== '' ? substr(hash('sha256', $hash), 0, 32) : null;
    }

    /**
     * @return class-string<Model>
     * @throws LogicException Si el modelo no existe
     */
    private function model(): string
    {
        if (! is_subclass_of($this->model, Model::class)) {
            throw new LogicException(
                "El modelo de usuario [{$this->model}] no existe o no extiende Model. Créalo o cambia config/auth.php (model)."
            );
        }

        return $this->model;
    }
}
