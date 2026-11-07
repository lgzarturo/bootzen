<?php

/**
 * Queue.php
 *
 * Contrato de la cola. Drivers: sync (ejecuta el job en el momento, sin tabla; el de los tests y
 * el de por defecto) y database (tabla jobs en SQLite o MySQL, la atiende queue:work).
 *
 * @package    BootZen\Queue
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.2.0
 */

declare(strict_types=1);

namespace BootZen\Queue;

interface Queue
{
    /**
     * Encola el job; con $delay (segundos) no se ejecuta antes de ese tiempo. Devuelve su id, o 0 en sync.
     */
    public function push(Job $job, int $delay = 0): int;
}
