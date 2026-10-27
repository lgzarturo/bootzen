<?php

declare(strict_types=1);

/**
 * Integración con MySQL real. Se ejecuta solo si existe BOOTZEN_TEST_MYSQL_HOST (CI la define).
 *
 * BOOTZEN_TEST_MYSQL_HOST, _PORT, _DATABASE, _USERNAME y _PASSWORD.
 */

use BootZen\Database\Connection;
use BootZen\Database\Migrator;
use BootZen\Database\Model;
use BootZen\Database\QueryException;

/**
 * @property int $id
 * @property string $titulo
 * @property bool $hecha
 * @property array<string, mixed>|null $meta
 */
final class MySqlTestTarea extends Model
{
    protected static string $table = 'tareas';

    protected static array $fillable = ['titulo', 'hecha', 'meta', 'proyecto_id'];

    protected static array $casts = ['hecha' => 'bool', 'meta' => 'array'];
}

function mysqlEnv(string $key, string $default = ''): string
{
    $value = getenv('BOOTZEN_TEST_MYSQL_' . $key);

    return $value === false ? $default : $value;
}

beforeEach(function (): void {
    if (mysqlEnv('HOST') === '') {
        $this->markTestSkipped('MySQL no configurado (BOOTZEN_TEST_MYSQL_HOST).');
    }

    $this->db = Connection::fromConfig([
        'driver' => 'mysql',
        'host' => mysqlEnv('HOST'),
        'port' => mysqlEnv('PORT', '3306'),
        'database' => mysqlEnv('DATABASE', 'bootzen_test'),
        'username' => mysqlEnv('USERNAME', 'root'),
        'password' => mysqlEnv('PASSWORD'),
    ], '/');

    $this->dir = makeTempDir('bootzen-mysql');
    writeFile($this->dir . '/migrations/2026_01_01_000000_crear_tablas.php', <<<'PHP'
        <?php
        use BootZen\Database\Connection;
        use BootZen\Database\Migration;

        return new class () extends Migration {
            public function up(Connection $db): void
            {
                $db->exec('CREATE TABLE proyectos (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL) ENGINE=InnoDB');
                $db->exec('CREATE TABLE tareas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    proyecto_id INT NULL,
                    titulo VARCHAR(200) NOT NULL,
                    hecha TINYINT(1) NOT NULL DEFAULT 0,
                    meta JSON NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
                ) ENGINE=InnoDB');
            }

            public function down(Connection $db): void
            {
                $db->exec('DROP TABLE tareas');
                $db->exec('DROP TABLE proyectos');
            }
        };
        PHP);

    $this->migrator = new Migrator($this->db, $this->dir . '/migrations');
    $this->migrator->dropAllTables();
    $this->migrator->run();
    Model::setConnectionResolver(fn () => $this->db);
});

afterEach(function (): void {
    Model::setConnectionResolver(null);

    if (isset($this->dir)) {
        removeDir($this->dir);
    }
});

it('migra, revierte y recrea el esquema en MySQL', function (): void {
    expect($this->migrator->status()[0]['ran'])->toBeTrue()
        ->and($this->migrator->rollback())->toBe(['2026_01_01_000000_crear_tablas'])
        ->and($this->db->select('SHOW TABLES'))->toHaveCount(1)
        ->and($this->migrator->fresh())->toBe(['2026_01_01_000000_crear_tablas'])
        ->and($this->db->select('SHOW TABLES'))->toHaveCount(3);
});

it('consulta con el query builder usando backticks y parámetros', function (): void {
    $proyecto = (int) $this->db->table('proyectos')->insert(['nombre' => 'Casa']);
    foreach (['Pan', 'Leche', 'Café'] as $i => $titulo) {
        $this->db->table('tareas')->insert(['titulo' => $titulo, 'hecha' => $i === 1, 'proyecto_id' => $proyecto]);
    }

    $query = $this->db->table('tareas')->where('hecha', false)->orderBy('titulo');

    expect($query->toSql())->toContain('`tareas`')
        ->and($query->pluck('titulo'))->toBe(['Café', 'Pan'])
        ->and($this->db->table('tareas')->join('proyectos', 'proyectos.id', '=', 'tareas.proyecto_id')->count())->toBe(3)
        ->and($this->db->table('tareas')->orderBy('id')->paginate(2, 2)->items()[0]['titulo'])->toBe('Café')
        ->and($this->db->table('tareas')->where('titulo', 'Pan')->update(['hecha' => true]))->toBe(1)
        ->and($this->db->table('tareas')->where('hecha', true)->delete())->toBe(2);
});

it('guarda y lee modelos con casts y claves foráneas', function (): void {
    $tarea = MySqlTestTarea::create(['titulo' => 'Pan', 'hecha' => true, 'meta' => ['nota' => 'ñ']]);
    $leida = MySqlTestTarea::findOrFail($tarea->id);

    expect($leida->hecha)->toBeTrue()
        ->and($leida->meta)->toBe(['nota' => 'ñ'])
        ->and($leida->created_at)->toBeInstanceOf(DateTimeImmutable::class)
        ->and(fn () => $this->db->table('tareas')->insert(['titulo' => 'x', 'proyecto_id' => 999]))->toThrow(QueryException::class);
});

it('revierte transacciones en InnoDB', function (): void {
    expect(fn () => $this->db->transaction(function (Connection $db): void {
        $db->table('tareas')->insert(['titulo' => 'temporal']);

        throw new RuntimeException('falla');
    }))->toThrow(RuntimeException::class)
        ->and($this->db->table('tareas')->count())->toBe(0);
});
