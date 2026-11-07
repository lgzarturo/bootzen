<?php

/**
 * DatabaseQueue.php
 *
 * Driver 'database' (QUEUE_DRIVER=database): guarda los jobs en la tabla jobs (SQLite o MySQL) y los
 * fallidos en failed_jobs. Las tablas las crea la migración de php bin/bootzen queue:table.
 *
 * @package    BootZen\Queue
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Queue;

use BootZen\Database\Connection;
use BootZen\Database\QueryException;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Un job reservado que no termina en $retryAfter segundos (el worker murió) vuelve a estar disponible.
 * Por eso $retryAfter debe ser mayor que lo que tarda el job más largo.
 *
 * @example $queue->push(new EnviarFactura(12), 300); // se ejecuta en 5 minutos o después
 */
final class DatabaseQueue implements Queue
{
    private readonly Closure $clock;

    /**
     * @param (Closure(): int)|null $clock Hora actual (para los tests)
     */
    public function __construct(
        private readonly Connection $db,
        private readonly int $retryAfter = 300,
        private readonly string $table = 'jobs',
        private readonly string $failedTable = 'failed_jobs',
        ?Closure $clock = null
    ) {
        foreach ([$table, $failedTable] as $name) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                throw new \InvalidArgumentException("Nombre de tabla inválido para la cola: {$name}");
            }
        }

        $this->clock = $clock ?? static fn (): int => time();
    }

    public function push(Job $job, int $delay = 0): int
    {
        $now = $this->now();

        return (int) $this->guard(fn (): string => $this->db->insert(
            "INSERT INTO {$this->table} (queue, class, payload, attempts, available_at, reserved_at, created_at) VALUES (?, ?, ?, 0, ?, NULL, ?)",
            [$job->queue, $job::class, json_encode($job->toPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $now + max(0, $delay), $now]
        ));
    }

    /**
     * Reserva el siguiente job disponible de la cola y suma un intento. Null si no hay ninguno.
     */
    public function pop(string $queue = 'default'): ?ReservedJob
    {
        return $this->guard(function () use ($queue): ?ReservedJob {
            // Dos workers pueden leer la misma fila: solo uno gana el UPDATE condicionado
            for ($try = 0; $try < 5; $try++) {
                $now = $this->now();
                $expired = $now - $this->retryAfter;
                $row = $this->db->selectOne(
                    "SELECT id, class, payload, attempts FROM {$this->table} WHERE queue = ? AND available_at <= ? AND (reserved_at IS NULL OR reserved_at <= ?) ORDER BY id LIMIT 1",
                    [$queue, $now, $expired]
                );

                if ($row === null) {
                    return null;
                }

                $won = $this->db->statement(
                    "UPDATE {$this->table} SET reserved_at = ?, attempts = attempts + 1 WHERE id = ? AND (reserved_at IS NULL OR reserved_at <= ?)",
                    [$now, $row['id'], $expired]
                );

                if ($won === 1) {
                    $payload = json_decode((string) $row['payload'], true);

                    return new ReservedJob((int) $row['id'], $queue, (string) $row['class'], is_array($payload) ? $payload : [], (int) $row['attempts'] + 1);
                }
            }

            return null;
        });
    }

    /**
     * Borra un job que terminó bien.
     */
    public function delete(ReservedJob $job): void
    {
        $this->db->statement("DELETE FROM {$this->table} WHERE id = ?", [$job->id]);
    }

    /**
     * Devuelve el job a la cola para reintentarlo dentro de $delay segundos.
     */
    public function release(ReservedJob $job, int $delay): void
    {
        $this->db->statement(
            "UPDATE {$this->table} SET reserved_at = NULL, available_at = ? WHERE id = ?",
            [$this->now() + max(0, $delay), $job->id]
        );
    }

    /**
     * Pasa el job a failed_jobs con el error.
     */
    public function fail(ReservedJob $job, Throwable $error): void
    {
        $this->db->transaction(function () use ($job, $error): void {
            $this->db->insert(
                "INSERT INTO {$this->failedTable} (queue, class, payload, attempts, error, failed_at) VALUES (?, ?, ?, ?, ?, ?)",
                [$job->queue, $job->class, json_encode($job->payload, JSON_UNESCAPED_UNICODE), $job->attempts, $error::class . ': ' . $error->getMessage(), $this->now()]
            );
            $this->delete($job);
        });
    }

    /**
     * Jobs pendientes (incluye los reservados).
     */
    public function size(string $queue = 'default'): int
    {
        return (int) $this->guard(fn (): mixed => $this->db->scalar("SELECT COUNT(*) FROM {$this->table} WHERE queue = ?", [$queue]));
    }

    /**
     * Jobs fallidos, del más reciente al más viejo.
     *
     * @return list<array{id: int, queue: string, class: string, attempts: int, error: string, failed_at: int}>
     */
    public function failed(): array
    {
        $rows = $this->guard(fn (): array => $this->db->select("SELECT id, queue, class, attempts, error, failed_at FROM {$this->failedTable} ORDER BY id DESC"));

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'queue' => (string) $row['queue'],
            'class' => (string) $row['class'],
            'attempts' => (int) $row['attempts'],
            'error' => (string) $row['error'],
            'failed_at' => (int) $row['failed_at'],
        ], $rows);
    }

    /**
     * Vuelve a encolar fallidos (todos con null) con los intentos en cero. Devuelve cuántos.
     */
    public function retry(?int $id = null): int
    {
        return $this->guard(fn (): int => $this->db->transaction(function () use ($id): int {
            $rows = $id === null
                ? $this->db->select("SELECT * FROM {$this->failedTable} ORDER BY id")
                : $this->db->select("SELECT * FROM {$this->failedTable} WHERE id = ?", [$id]);
            $now = $this->now();

            foreach ($rows as $row) {
                $this->db->insert(
                    "INSERT INTO {$this->table} (queue, class, payload, attempts, available_at, reserved_at, created_at) VALUES (?, ?, ?, 0, ?, NULL, ?)",
                    [$row['queue'], $row['class'], $row['payload'], $now, $now]
                );
                $this->db->statement("DELETE FROM {$this->failedTable} WHERE id = ?", [$row['id']]);
            }

            return count($rows);
        }));
    }

    /**
     * Borra los fallidos. Devuelve cuántos.
     */
    public function flushFailed(): int
    {
        return $this->guard(fn (): int => $this->db->statement("DELETE FROM {$this->failedTable}"));
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    /**
     * Traduce "no existe la tabla" a una instrucción clara.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    private function guard(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $e) {
            if (preg_match('/no such table|doesn\'t exist|42S02/i', $e->getMessage()) === 1) {
                throw new RuntimeException('Falta la tabla de la cola. Créala con php bin/bootzen queue:table y php bin/bootzen migrate.', 0, $e);
            }

            throw $e;
        }
    }
}
