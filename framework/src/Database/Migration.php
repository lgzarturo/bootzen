<?php

/**
 * Migration.php
 *
 * Base de las migraciones. Cada archivo de database/migrations retorna una clase anónima que la
 * extiende y escribe SQL directo:
 *
 *   return new class () extends Migration {
 *       public function up(Connection $db): void
 *       {
 *           $db->exec('CREATE TABLE tareas (id INTEGER PRIMARY KEY AUTOINCREMENT, titulo TEXT NOT NULL)');
 *       }
 *
 *       public function down(Connection $db): void
 *       {
 *           $db->exec('DROP TABLE tareas');
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
 * Migración de esquema.
 */
abstract class Migration
{
    /**
     * Aplica el cambio.
     */
    abstract public function up(Connection $db): void;

    /**
     * Revierte el cambio. Si no se implementa, la migración no se puede revertir.
     *
     * @throws LogicException Si la migración no es reversible
     */
    public function down(Connection $db): void
    {
        throw new LogicException(static::class . ' no se puede revertir: implementa down().');
    }
}
