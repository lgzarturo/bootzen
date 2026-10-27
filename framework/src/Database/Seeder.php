<?php

/**
 * Seeder.php
 *
 * Base de los seeders (datos de ejemplo o iniciales). Cada archivo de database/seeds retorna una
 * clase anónima que la extiende:
 *
 *   return new class () extends Seeder {
 *       public function run(Connection $db): void
 *       {
 *           $db->table('tareas')->insert(['titulo' => 'Primera tarea']);
 *           $this->call('Usuarios'); // ejecuta database/seeds/Usuarios.php
 *       }
 *   };
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use LogicException;

/**
 * Seeder de datos.
 */
abstract class Seeder
{
    private ?SeederRunner $runner = null;

    abstract public function run(Connection $db): void;

    /**
     * @internal Lo asigna SeederRunner
     */
    public function setRunner(SeederRunner $runner): void
    {
        $this->runner = $runner;
    }

    /**
     * Ejecuta otro seeder de database/seeds por nombre de archivo (sin .php).
     *
     * @throws LogicException Si el seeder no se ejecuta con SeederRunner
     */
    protected function call(string $name): void
    {
        if ($this->runner === null) {
            throw new LogicException('call() solo funciona cuando el seeder se ejecuta con db:seed.');
        }

        $this->runner->run($name);
    }
}
