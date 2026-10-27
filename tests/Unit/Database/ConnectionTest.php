<?php

declare(strict_types=1);

use BootZen\Database\Connection;
use BootZen\Database\QueryException;

it('ejecuta consultas con parámetros enlazados', function (): void {
    $db = memoryDb();
    $id = $db->insert('INSERT INTO tareas (titulo, hecha) VALUES (?, ?)', ['Pan', true]);

    expect($id)->toBe('1')
        ->and($db->selectOne('SELECT titulo, hecha FROM tareas WHERE id = ?', [1]))->toBe(['titulo' => 'Pan', 'hecha' => 1])
        ->and($db->select('SELECT id FROM tareas'))->toBe([['id' => 1]])
        ->and($db->scalar('SELECT count(*) FROM tareas'))->toBe(1)
        ->and($db->statement('UPDATE tareas SET hecha = ? WHERE id = ?', [false, 1]))->toBe(1)
        ->and($db->selectOne('SELECT * FROM tareas WHERE id = ?', [99]))->toBeNull()
        ->and($db->scalar('SELECT titulo FROM tareas WHERE id = ?', [99]))->toBeNull();
});

it('guarda fechas, booleanos, null y Stringable', function (): void {
    $db = memoryDb();
    $db->insert(
        'INSERT INTO tareas (titulo, hecha, prioridad, vence_en) VALUES (?, ?, ?, ?)',
        [new BootZen\View\HtmlString('Pan'), false, null, new DateTimeImmutable('2026-10-27 08:30:00')]
    );

    expect($db->selectOne('SELECT titulo, hecha, prioridad, vence_en FROM tareas'))->toBe([
        'titulo' => 'Pan',
        'hecha' => 0,
        'prioridad' => null,
        'vence_en' => '2026-10-27 08:30:00',
    ]);
});

it('rechaza valores que no se pueden enviar', function (): void {
    memoryDb()->select('SELECT ?', [['array']]);
})->throws(InvalidArgumentException::class, 'no se puede enviar a la base de datos');

it('incluye el SQL y los valores en los errores', function (): void {
    try {
        memoryDb()->select('SELECT * FROM no_existe WHERE x = ?', [5]);
        $this->fail('Se esperaba QueryException');
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain('no such table: no_existe')
            ->and($e->getMessage())->toContain('(SQL: SELECT * FROM no_existe WHERE x = ?) (valores: [5])')
            ->and($e->getSql())->toBe('SELECT * FROM no_existe WHERE x = ?')
            ->and($e->getBindings())->toBe([5]);
    }
});

it('confirma o revierte transacciones, incluso anidadas', function (): void {
    $db = memoryDb();

    $db->transaction(function (Connection $db): void {
        $db->insert('INSERT INTO tareas (titulo) VALUES (?)', ['uno']);
        $db->transaction(fn (Connection $db) => $db->insert('INSERT INTO tareas (titulo) VALUES (?)', ['dos']));
    });

    expect(fn () => $db->transaction(function (Connection $db): void {
        $db->insert('INSERT INTO tareas (titulo) VALUES (?)', ['tres']);

        throw new RuntimeException('falla');
    }))->toThrow(RuntimeException::class, 'falla')
        ->and($db->scalar('SELECT count(*) FROM tareas'))->toBe(2)
        ->and($db->pdo()->inTransaction())->toBeFalse();
});

it('configura SQLite en archivo con WAL y claves foráneas', function (): void {
    $directory = makeTempDir();
    $db = Connection::fromConfig(['driver' => 'sqlite', 'database' => 'database/app.sqlite'], $directory);

    expect($db->driver())->toBe('sqlite')
        ->and($directory . '/database/app.sqlite')->toBeFile()
        ->and($db->scalar('PRAGMA journal_mode'))->toBe('wal')
        ->and($db->scalar('PRAGMA foreign_keys'))->toBe(1)
        ->and($db->scalar('PRAGMA busy_timeout'))->toBe(5000);

    removeDir($directory);
});

it('rechaza drivers y modos de journal desconocidos', function (array $config, string $message): void {
    Connection::fromConfig($config, sys_get_temp_dir() . '/bootzen-' . bin2hex(random_bytes(4)));
})->throws(InvalidArgumentException::class)->with([
    'driver' => [['driver' => 'oracle'], 'no soportado'],
    'journal' => [['driver' => 'sqlite', 'database' => 'x.sqlite', 'journal_mode' => 'raro'], 'journal_mode'],
]);

it('informa los errores de conexión como QueryException', function (): void {
    Connection::fromConfig(['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '1', 'database' => 'x'], '/');
})->throws(QueryException::class, '(conexión)');

it('encierra identificadores entre comillas del driver', function (): void {
    expect(memoryDb(false)->quote('tareas.titulo'))->toBe('"tareas"."titulo"')
        ->and(memoryDb(false)->quote('t.*'))->toBe('"t".*')
        ->and((new Connection(new PDO('sqlite::memory:'), 'mysql'))->quote('tareas.id'))->toBe('`tareas`.`id`');
});
