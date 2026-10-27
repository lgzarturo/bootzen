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
     * Hash de relleno para verificar cuando el usuario no existe y tardar lo mismo.
     */
    private static ?string $dummyHash = null;

    /**
     * Usuario cargado y la sesión para la que se cargó.
     */
    private ?Model $user = null;

    private ?Session $loadedFor = null;

    /**
     * @param Closure(): ?Session $session Sesión de la petición actual (null fuera del grupo web)
     * @param string $model Clase del modelo de usuario
     */
    public function __construct(private readonly Closure $session, private readonly string $model)
    {
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
            $this->user = is_int($id) || is_string($id) ? $this->model()::find($id) : null;
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
     */
    public function attempt(array $credentials): bool
    {
        $user = $this->validate($credentials);

        if ($user === null) {
            return false;
        }

        $this->login($user);

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
     * Inicia sesión como el usuario. Cambia el id de la sesión y el token CSRF para evitar fijación de sesión.
     */
    public function login(Model $user): void
    {
        $session = $this->session();
        $session->regenerate();
        $session->regenerateToken();
        $session->put(self::SESSION_KEY, $user->getKey());

        $this->user = $user;
        $this->loadedFor = $session;
    }

    /**
     * Cierra la sesión: borra todos sus datos y cambia el id.
     */
    public function logout(): void
    {
        $session = $this->session();
        $session->invalidate();

        $this->user = null;
        $this->loadedFor = $session;
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
