<?php

/**
 * ParameterizedMiddleware.php
 *
 * Middleware que recibe parámetros desde la ruta: ->middleware('throttle:60,1').
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Core;

/**
 * Lo que va después de los dos puntos se separa por comas y llega a withParameters().
 */
interface ParameterizedMiddleware extends MiddlewareInterface
{
    /**
     * Devuelve una copia configurada con los parámetros.
     *
     * @param list<string> $parameters
     * @throws \InvalidArgumentException Si los parámetros no son válidos
     */
    public function withParameters(array $parameters): static;
}
