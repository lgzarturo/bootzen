<?php

/**
 * TestClient.php
 *
 * Cliente para tests de feature: envía peticiones a la aplicación sin servidor, conserva la cookie de
 * sesión entre peticiones como un navegador y trae aserciones de base de datos y autenticación.
 *
 * @package    BootZen\Testing
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Testing;

use BootZen\Core\Application;
use BootZen\Core\Request;
use BootZen\Database\Connection;
use BootZen\Database\Model;
use BootZen\Session\Session;
use BootZen\Session\SessionStore;
use LogicException;
use PHPUnit\Framework\Assert;

/**
 * En los tests del proyecto se obtiene con client() (tests/Pest.php).
 *
 * @example client()->get('/tareas')->assertOk()->assertSee('Comprar pan');
 * @example client()->actingAs($usuario)->post('/tareas', ['titulo' => 'Pan'])->assertRedirect('/tareas/1');
 */
final class TestClient
{
    /**
     * Cookies que la aplicación envió: nombre => valor.
     *
     * @var array<string, string>
     */
    private array $cookies = [];

    /**
     * Cabeceras que se agregan a todas las peticiones.
     *
     * @var array<string, string>
     */
    private array $headers = [];

    private bool $followRedirects = false;

    public function __construct(public readonly Application $app)
    {
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, [], $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PUT', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PATCH', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, $data, $headers);
    }

    /**
     * GET pidiendo JSON (cabecera Accept: application/json).
     */
    public function getJson(string $uri): TestResponse
    {
        return $this->request('GET', $uri, [], ['Accept' => 'application/json']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function postJson(string $uri, array $data = []): TestResponse
    {
        return $this->request('POST', $uri, $data, ['Accept' => 'application/json', 'Content-Type' => 'application/json']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function putJson(string $uri, array $data = []): TestResponse
    {
        return $this->request('PUT', $uri, $data, ['Accept' => 'application/json', 'Content-Type' => 'application/json']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function deleteJson(string $uri, array $data = []): TestResponse
    {
        return $this->request('DELETE', $uri, $data, ['Accept' => 'application/json', 'Content-Type' => 'application/json']);
    }

    /**
     * Envía una petición. Los archivos usan la estructura de $_FILES.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @param array<string, array<string, mixed>> $files
     */
    public function request(string $method, string $uri, array $data = [], array $headers = [], array $files = []): TestResponse
    {
        $request = new Request(
            $method,
            $uri,
            array_replace($this->headers, $headers),
            $data,
            $files,
            ['REMOTE_ADDR' => '127.0.0.1', 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            $this->cookies
        );

        $response = new TestResponse($this->app->run($request), $this);
        $this->storeCookies($response);

        for ($hops = 0; $this->followRedirects && $response->isRedirect() && $hops < 5; $hops++) {
            $location = (string) $response->response->getHeader('location');
            $response = new TestResponse($this->app->run(new Request('GET', $location, $this->headers, [], [], ['REMOTE_ADDR' => '127.0.0.1'], $this->cookies)), $this);
            $this->storeCookies($response);
        }

        return $response;
    }

    /**
     * Agrega cabeceras a todas las peticiones siguientes.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_replace($this->headers, $headers);

        return $this;
    }

    /**
     * Simula que el usuario viene de esa página (cabecera Referer): tras un error de validación,
     * la respuesta redirige ahí.
     */
    public function from(string $uri): self
    {
        return $this->withHeaders(['Referer' => $uri]);
    }

    /**
     * Las siguientes peticiones siguen las redirecciones (hasta 5) y devuelven la página final.
     */
    public function followingRedirects(): self
    {
        $this->followRedirects = true;

        return $this;
    }

    /**
     * Inicia sesión como el usuario sin pasar por el formulario de login.
     */
    public function actingAs(Model $user): self
    {
        $data = $this->sessionData();
        $data['_auth_id'] = $user->getKey();
        $this->writeSession($data);

        return $this;
    }

    /**
     * Datos de la sesión actual del cliente ([] si todavía no hay sesión).
     *
     * @return array<string, mixed>
     */
    public function sessionData(): array
    {
        $id = $this->cookies[$this->cookieName()] ?? null;

        return $id === null ? [] : ($this->store()->read($id) ?? []);
    }

    public function assertAuthenticated(): self
    {
        Assert::assertArrayHasKey('_auth_id', $this->sessionData(), 'Se esperaba un usuario autenticado y no hay sesión iniciada.');

        return $this;
    }

    public function assertGuest(): self
    {
        Assert::assertArrayNotHasKey('_auth_id', $this->sessionData(), 'Se esperaba un invitado y hay una sesión iniciada.');

        return $this;
    }

    /**
     * Comprueba que exista al menos una fila con esos valores.
     *
     * @param array<string, mixed> $values Columna => valor
     */
    public function assertDatabaseHas(string $table, array $values): self
    {
        Assert::assertTrue(
            $this->whereAll($table, $values)->exists(),
            "No hay ninguna fila en {$table} con " . json_encode($values, JSON_UNESCAPED_UNICODE) . '.'
        );

        return $this;
    }

    /**
     * @param array<string, mixed> $values Columna => valor
     */
    public function assertDatabaseMissing(string $table, array $values): self
    {
        Assert::assertFalse(
            $this->whereAll($table, $values)->exists(),
            "Hay una fila en {$table} con " . json_encode($values, JSON_UNESCAPED_UNICODE) . ' y no debería.'
        );

        return $this;
    }

    public function assertDatabaseCount(string $table, int $count): self
    {
        $actual = $this->db()->table($table)->count();
        Assert::assertSame($count, $actual, "Se esperaban {$count} filas en {$table} y hay {$actual}.");

        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function whereAll(string $table, array $values): \BootZen\Database\QueryBuilder
    {
        $query = $this->db()->table($table);

        foreach ($values as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    private function storeCookies(TestResponse $response): void
    {
        foreach ($response->response->getCookies() as $name => $cookie) {
            $value = $cookie['value'] ?? '';
            $expires = $cookie['expires'] ?? 0;

            if ($value === '' || (is_int($expires) && $expires !== 0 && $expires < time())) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = is_scalar($value) ? (string) $value : '';
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeSession(array $data): void
    {
        $name = $this->cookieName();
        $id = $this->cookies[$name] ?? null;

        if ($id === null || $this->store()->read($id) === null) {
            $id = Session::generateId();
        }

        $this->store()->write($id, $data);
        $this->cookies[$name] = $id;
    }

    private function cookieName(): string
    {
        $name = $this->app->config()->get('session.cookie', 'bootzen_session');

        return is_string($name) ? $name : 'bootzen_session';
    }

    private function store(): SessionStore
    {
        $store = $this->app->make(SessionStore::class);

        return $store instanceof SessionStore ? $store : throw new LogicException('El servicio SessionStore no está disponible.');
    }

    private function db(): Connection
    {
        $db = $this->app->make(Connection::class);

        return $db instanceof Connection ? $db : throw new LogicException('El servicio Connection no está disponible.');
    }
}
