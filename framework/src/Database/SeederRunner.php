<?php

/**
 * SeederRunner.php
 *
 * Ejecuta seeders de database/seeds por nombre de archivo. db:seed corre DatabaseSeeder por defecto.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use UnexpectedValueException;

/**
 * Ejecutor de seeders.
 */
final class SeederRunner
{
    /**
     * Seeders ejecutados, en orden.
     *
     * @var list<string>
     */
    private array $ran = [];

    /**
     * @param string $path Directorio de seeders
     */
    public function __construct(private readonly Connection $db, private readonly string $path)
    {
    }

    /**
     * Ejecuta un seeder (y los que llame con call()).
     *
     * @return list<string> Seeders ejecutados
     * @throws UnexpectedValueException Si el archivo no existe o no retorna un Seeder
     */
    public function run(string $name = 'DatabaseSeeder'): array
    {
        if (preg_match('/^[A-Za-z0-9_\-\/]+$/', $name) !== 1 || str_contains($name, '..')) {
            throw new UnexpectedValueException("Nombre de seeder inválido: [{$name}].");
        }

        $file = rtrim($this->path, '/') . '/' . $name . '.php';

        if (! is_file($file)) {
            throw new UnexpectedValueException("No existe el seeder [{$name}]: crea {$file}.");
        }

        $seeder = require $file;

        if (! $seeder instanceof Seeder) {
            throw new UnexpectedValueException(
                "El seeder [{$name}] debe retornar una clase que extienda " . Seeder::class
                . ': return new class () extends Seeder { ... };'
            );
        }

        $seeder->setRunner($this);
        $this->db->transaction(static fn (Connection $db) => $seeder->run($db));
        $this->ran[] = $name;

        return $this->ran;
    }
}
