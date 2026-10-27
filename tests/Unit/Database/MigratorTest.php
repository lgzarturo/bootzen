<?php

declare(strict_types=1);

use BootZen\Database\MigrationException;
use BootZen\Database\Migrator;
use BootZen\Database\SeederRunner;

function migrationFile(string $directory, string $name, string $up, string $down = ''): void
{
    $downMethod = $down === '' ? '' : "public function down(Connection \$db): void { \$db->exec('{$down}'); }";

    writeFile($directory . "/migrations/{$name}.php", <<<PHP
        <?php
        use BootZen\\Database\\Connection;
        use BootZen\\Database\\Migration;

        return new class () extends Migration {
            public function up(Connection \$db): void { \$db->exec('{$up}'); }
            {$downMethod}
        };
        PHP);
}

beforeEach(function (): void {
    $this->dir = makeTempDir('bootzen-migraciones');
    $this->db = memoryDb(false);
    $this->migrator = new Migrator($this->db, $this->dir . '/migrations');
    migrationFile($this->dir, '2026_01_01_000000_crear_proyectos', 'CREATE TABLE proyectos (id INTEGER PRIMARY KEY, nombre TEXT)', 'DROP TABLE proyectos');
    migrationFile($this->dir, '2026_01_02_000000_crear_tareas', 'CREATE TABLE tareas (id INTEGER PRIMARY KEY, titulo TEXT)', 'DROP TABLE tareas');
});

afterEach(function (): void {
    removeDir($this->dir);
});

function tables($db): array
{
    return array_column($db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
}

it('ejecuta las migraciones pendientes en orden y por lotes', function (): void {
    expect($this->migrator->run())->toBe(['2026_01_01_000000_crear_proyectos', '2026_01_02_000000_crear_tareas'])
        ->and($this->migrator->run())->toBe([])
        ->and(tables($this->db))->toBe(['migrations', 'proyectos', 'tareas']);

    migrationFile($this->dir, '2026_01_03_000000_crear_notas', 'CREATE TABLE notas (id INTEGER PRIMARY KEY)', 'DROP TABLE notas');

    expect($this->migrator->run())->toBe(['2026_01_03_000000_crear_notas'])
        ->and($this->migrator->status())->toBe([
            ['migration' => '2026_01_01_000000_crear_proyectos', 'ran' => true, 'batch' => 1],
            ['migration' => '2026_01_02_000000_crear_tareas', 'ran' => true, 'batch' => 1],
            ['migration' => '2026_01_03_000000_crear_notas', 'ran' => true, 'batch' => 2],
        ]);
});

it('revierte el último lote o varios', function (): void {
    $this->migrator->run();
    migrationFile($this->dir, '2026_01_03_000000_crear_notas', 'CREATE TABLE notas (id INTEGER PRIMARY KEY)', 'DROP TABLE notas');
    $this->migrator->run();

    expect($this->migrator->rollback())->toBe(['2026_01_03_000000_crear_notas'])
        ->and(tables($this->db))->toBe(['migrations', 'proyectos', 'tareas'])
        ->and($this->migrator->rollback(5))->toBe(['2026_01_02_000000_crear_tareas', '2026_01_01_000000_crear_proyectos'])
        ->and(tables($this->db))->toBe(['migrations'])
        ->and($this->migrator->rollback())->toBe([]);
});

it('marca como pendientes las migraciones que no corrieron', function (): void {
    expect(array_column($this->migrator->status(), 'ran'))->toBe([false, false]);
});

it('deshace una migración que falla a la mitad', function (): void {
    migrationFile($this->dir, '2026_01_03_000000_rota', 'CREATE TABLE a (id INTEGER); CREATE TABLE tareas (x INTEGER)');

    expect(fn () => $this->migrator->run())->toThrow(MigrationException::class, 'Falló la migración [2026_01_03_000000_rota]')
        ->and(tables($this->db))->not->toContain('a')
        ->and(array_column($this->migrator->status(), 'ran'))->toBe([true, true, false]);
});

it('borra todo y migra de nuevo con fresh', function (): void {
    $this->migrator->run();
    $this->db->exec('CREATE TABLE extra (id INTEGER)');

    expect($this->migrator->fresh())->toHaveCount(2)
        ->and(tables($this->db))->toBe(['migrations', 'proyectos', 'tareas']);
});

it('valida archivos de migración, reversibilidad y archivos faltantes', function (): void {
    writeFile($this->dir . '/migrations/2026_01_05_000000_invalida.php', '<?php return 42;');

    expect(fn () => $this->migrator->run())->toThrow(MigrationException::class, 'debe retornar una clase que extienda');

    unlink($this->dir . '/migrations/2026_01_05_000000_invalida.php');
    migrationFile($this->dir, '2026_01_06_000000_sin_down', 'CREATE TABLE x (id INTEGER)');
    $this->migrator->run();

    expect(fn () => $this->migrator->rollback())->toThrow(MigrationException::class, 'no se puede revertir');

    unlink($this->dir . '/migrations/2026_01_06_000000_sin_down.php');

    expect(fn () => $this->migrator->rollback())->toThrow(UnexpectedValueException::class, 'No existe el archivo de la migración');
});

it('ejecuta seeders y los que llaman con call()', function (): void {
    $this->migrator->run();
    writeFile($this->dir . '/seeds/DatabaseSeeder.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Seeder;

        return new class () extends Seeder {
            public function run(Connection $db): void
            {
                $db->table('proyectos')->insert(['nombre' => 'Casa']);
                $this->call('Tareas');
            }
        };
        PHP);
    writeFile($this->dir . '/seeds/Tareas.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Seeder;

        return new class () extends Seeder {
            public function run(Connection $db): void
            {
                $db->table('tareas')->insert(['titulo' => 'Pan']);
            }
        };
        PHP);

    $runner = new SeederRunner($this->db, $this->dir . '/seeds');

    expect($runner->run())->toBe(['Tareas', 'DatabaseSeeder'])
        ->and($this->db->table('tareas')->count())->toBe(1)
        ->and($this->db->table('proyectos')->count())->toBe(1);
});

it('valida los seeders', function (string $name, string $contents, string $message): void {
    if ($contents !== '') {
        writeFile($this->dir . "/seeds/{$name}.php", $contents);
    }

    expect(fn () => (new SeederRunner($this->db, $this->dir . '/seeds'))->run($name))->toThrow(UnexpectedValueException::class, $message);
})->with([
    'inexistente' => ['Nada', '', 'No existe el seeder [Nada]'],
    'ruta inválida' => ['../fuera', '', 'Nombre de seeder inválido'],
    'no es Seeder' => ['Malo', '<?php return new stdClass();', 'debe retornar una clase que extienda'],
]);

it('call() falla fuera de SeederRunner', function (): void {
    $seeder = new class () extends BootZen\Database\Seeder {
        public function run(BootZen\Database\Connection $db): void
        {
            $this->call('Otro');
        }
    };

    $seeder->run($this->db);
})->throws(LogicException::class, 'solo funciona cuando el seeder se ejecuta con db:seed');
