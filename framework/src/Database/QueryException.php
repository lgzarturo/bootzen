<?php

/**
 * QueryException.php
 *
 * Error de base de datos que incluye la consulta SQL y sus valores, para depurar sin adivinar.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use RuntimeException;
use Throwable;

/**
 * Falla al ejecutar una consulta.
 */
class QueryException extends RuntimeException
{
    /**
     * @param list<mixed> $bindings
     */
    public function __construct(
        private readonly string $sql,
        private readonly array $bindings,
        Throwable $previous
    ) {
        parent::__construct(
            sprintf('%s (SQL: %s) (valores: %s)', $previous->getMessage(), $sql, self::describe($bindings)),
            0,
            $previous
        );
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * @param list<mixed> $bindings
     */
    private static function describe(array $bindings): string
    {
        $encoded = json_encode($bindings, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded === false ? '[]' : $encoded;
    }
}
