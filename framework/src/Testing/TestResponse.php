<?php

/**
 * TestResponse.php
 *
 * Respuesta de TestClient con aserciones encadenables: estado, redirecciones, HTML, JSON, cabeceras,
 * sesión y errores de validación. Los mensajes de error dicen qué llegó en lugar de lo esperado.
 *
 * @package    BootZen\Testing
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Testing;

use BootZen\Core\Response;
use PHPUnit\Framework\Assert;

/**
 * @example client()->post('/tareas', [])->assertSessionHasErrors(['titulo']);
 * @example client()->getJson('/api/tareas')->assertOk()->assertJsonPath('data.0.titulo', 'Pan');
 */
final class TestResponse
{
    public function __construct(public readonly Response $response, private readonly TestClient $client)
    {
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function body(): string
    {
        return $this->response->getBody();
    }

    public function header(string $name): ?string
    {
        return $this->response->getHeader($name);
    }

    public function isRedirect(): bool
    {
        return $this->response->isRedirect() && $this->header('location') !== null;
    }

    /**
     * Cuerpo JSON decodificado; con $path, el valor en esa ruta ('data.0.titulo').
     */
    public function json(?string $path = null): mixed
    {
        $data = json_decode($this->body(), true);
        Assert::assertTrue(json_last_error() === JSON_ERROR_NONE, 'La respuesta no es JSON válido: ' . $this->excerpt());

        if ($path === null) {
            return $data;
        }

        foreach (explode('.', $path) as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                Assert::fail("El JSON no tiene la ruta [{$path}]: " . $this->excerpt());
            }

            $data = $data[$segment];
        }

        return $data;
    }

    public function assertStatus(int $status): self
    {
        Assert::assertSame(
            $status,
            $this->status(),
            "Se esperaba el estado {$status} y llegó {$this->status()}." . $this->hint()
        );

        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    public function assertNoContent(): self
    {
        return $this->assertStatus(204);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertUnprocessable(): self
    {
        return $this->assertStatus(422);
    }

    /**
     * Comprueba que sea una redirección y, si se indica, a qué path (se ignora el dominio).
     */
    public function assertRedirect(?string $uri = null): self
    {
        Assert::assertTrue(
            $this->isRedirect(),
            "Se esperaba una redirección y llegó el estado {$this->status()}." . $this->hint()
        );

        if ($uri !== null) {
            Assert::assertSame(self::pathOf($uri), self::pathOf((string) $this->header('location')), 'La redirección va a otro lugar.');
        }

        return $this;
    }

    /**
     * Comprueba que el cuerpo contenga el texto (escapado como lo imprime {{ }}, salvo $escape = false).
     */
    public function assertSee(string $text, bool $escape = true): self
    {
        $needle = $escape ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        Assert::assertStringContainsString($needle, $this->body(), "No aparece \"{$text}\" en la respuesta.");

        return $this;
    }

    public function assertDontSee(string $text, bool $escape = true): self
    {
        $needle = $escape ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : $text;
        Assert::assertStringNotContainsString($needle, $this->body(), "Aparece \"{$text}\" en la respuesta y no debería.");

        return $this;
    }

    /**
     * Comprueba que el JSON contenga estos valores (puede tener más).
     *
     * @param array<mixed> $subset
     */
    public function assertJson(array $subset): self
    {
        $data = $this->json();
        Assert::assertIsArray($data, 'La respuesta JSON no es un objeto ni una lista.');
        self::assertSubset($subset, $data, '');

        return $this;
    }

    public function assertJsonPath(string $path, mixed $expected): self
    {
        Assert::assertSame($expected, $this->json($path), "El valor de [{$path}] en el JSON no coincide.");

        return $this;
    }

    /**
     * Cantidad de elementos del JSON o de la lista en $path.
     */
    public function assertJsonCount(int $count, ?string $path = null): self
    {
        $value = $this->json($path);
        Assert::assertIsArray($value, 'Se esperaba una lista en el JSON.');
        Assert::assertCount($count, $value);

        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): self
    {
        $actual = $this->header($name);
        Assert::assertNotNull($actual, "La respuesta no tiene la cabecera {$name}.");

        if ($value !== null) {
            Assert::assertSame($value, $actual, "La cabecera {$name} no coincide.");
        }

        return $this;
    }

    /**
     * Comprueba que la sesión tenga la clave (y el valor, si se indica). Sirve para mensajes flash.
     */
    public function assertSessionHas(string $key, mixed $value = null): self
    {
        $session = $this->client->sessionData();
        Assert::assertArrayHasKey($key, $session, "La sesión no tiene la clave [{$key}].");

        if (func_num_args() > 1) {
            Assert::assertSame($value, $session[$key], "El valor de [{$key}] en la sesión no coincide.");
        }

        return $this;
    }

    public function assertSessionMissing(string $key): self
    {
        Assert::assertArrayNotHasKey($key, $this->client->sessionData(), "La sesión tiene la clave [{$key}] y no debería.");

        return $this;
    }

    /**
     * Comprueba que haya errores de validación en esos campos (o en alguno, si no se indican).
     *
     * @param string|list<string> $fields
     */
    public function assertSessionHasErrors(string|array $fields = []): self
    {
        $errors = $this->errors();
        Assert::assertNotSame([], $errors, 'No hay errores de validación en la sesión.' . $this->hint());

        foreach ((array) $fields as $field) {
            Assert::assertArrayHasKey(
                $field,
                $errors,
                "No hay error de validación para [{$field}]. Errores: " . json_encode($errors, JSON_UNESCAPED_UNICODE)
            );
        }

        return $this;
    }

    public function assertSessionHasNoErrors(): self
    {
        $errors = $this->errors();
        Assert::assertSame([], $errors, 'Hay errores de validación: ' . json_encode($errors, JSON_UNESCAPED_UNICODE));

        return $this;
    }

    /**
     * @return array<mixed>
     */
    private function errors(): array
    {
        $errors = $this->client->sessionData()['_errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }

    /**
     * @param array<mixed> $subset
     * @param array<mixed> $data
     */
    private static function assertSubset(array $subset, array $data, string $path): void
    {
        foreach ($subset as $key => $expected) {
            $current = $path === '' ? (string) $key : $path . '.' . $key;
            Assert::assertArrayHasKey($key, $data, "El JSON no tiene la clave [{$current}].");

            if (is_array($expected) && is_array($data[$key])) {
                self::assertSubset($expected, $data[$key], $current);

                continue;
            }

            Assert::assertSame($expected, $data[$key], "El valor de [{$current}] en el JSON no coincide.");
        }
    }

    private static function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return (is_string($path) && $path !== '' ? $path : '/') . (is_string($query) ? '?' . $query : '');
    }

    /**
     * Pista para el mensaje de error: a dónde redirige o el inicio del cuerpo.
     */
    private function hint(): string
    {
        return $this->isRedirect()
            ? ' Redirige a ' . $this->header('location') . '.'
            : ' Respuesta: ' . $this->excerpt();
    }

    private function excerpt(): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($this->body())));

        return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '...' : $text;
    }
}
