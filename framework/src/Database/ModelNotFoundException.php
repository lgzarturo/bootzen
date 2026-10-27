<?php

/**
 * ModelNotFoundException.php
 *
 * La lanza findOrFail()/firstOrFail() cuando no existe el registro. Es un 404: si no se captura,
 * el usuario ve la página de error 404 (o JSON) sin que el controlador tenga que programarlo.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use BootZen\Core\HttpException;

/**
 * Registro no encontrado (HTTP 404).
 */
final class ModelNotFoundException extends HttpException
{
    /**
     * @param class-string<Model> $model
     */
    public function __construct(private readonly string $model, private readonly int|string|null $id = null)
    {
        parent::__construct(404, 'Registro no encontrado.');
    }

    /**
     * @return class-string<Model>
     */
    public function getModel(): string
    {
        return $this->model;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }
}
