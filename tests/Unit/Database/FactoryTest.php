<?php

declare(strict_types=1);

use BootZen\Database\Model;

require_once dirname(__DIR__, 2) . '/Fixtures/factories.php';

beforeEach(function (): void {
    $this->db = memoryDb();
    $this->db->exec('CREATE TABLE clientes (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, email TEXT NOT NULL UNIQUE, role TEXT NOT NULL, created_at TEXT, updated_at TEXT)');
    $this->db->exec('CREATE TABLE pedidos (id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NOT NULL, titulo TEXT NOT NULL, pagado INTEGER NOT NULL, total REAL NOT NULL)');
    Model::setConnectionResolver(fn () => $this->db);
});

afterEach(fn () => Model::setConnectionResolver(null));

it('crea un registro con todas las columnas de la definición, también las que no son fillable', function (): void {
    $cliente = FactoryCliente::factory()->create(['nombre' => 'Ana']);

    expect($cliente->exists())->toBeTrue()
        ->and($cliente->nombre)->toBe('Ana')
        ->and($cliente->role)->toBe('cliente')
        ->and($cliente->email)->toMatch('/^[a-z.]+\d+@example\.(com|org|net)$/')
        ->and(FactoryCliente::find($cliente->id)?->role)->toBe('cliente');
});

it('crea la relación de una factory anidada y resuelve closures con los demás valores', function (): void {
    $pedido = FactoryPedido::factory()->state(['pagado' => true])->create();

    expect($pedido->cliente_id)->toBe(1)
        ->and($pedido->total)->toBe(100.0)
        ->and(FactoryCliente::query()->count())->toBe(1);

    // Con el id dado no crea otro cliente
    FactoryPedido::factory()->create(['cliente_id' => 1]);
    expect(FactoryCliente::query()->count())->toBe(1);
});

it('crea varios en un lote con estados por posición y afterCreating', function (): void {
    $cliente = FactoryCliente::factory()->create();
    $creados = [];

    $pedidos = FactoryPedido::factory()
        ->afterCreating(function (FactoryPedido $pedido) use (&$creados): void {
            $creados[] = $pedido->id;
        })
        ->count(3)
        ->state(fn (array $valores, int $i): array => ['titulo' => "Pedido {$i}"])
        ->create(['cliente_id' => $cliente->id]);

    expect($pedidos)->toHaveCount(3)
        ->and(array_map(fn (FactoryPedido $p): string => $p->titulo, $pedidos))->toBe(['Pedido 0', 'Pedido 1', 'Pedido 2'])
        ->and($creados)->toBe([1, 2, 3])
        ->and(FactoryPedido::query()->count())->toBe(3);
});

it('make() arma modelos sin guardarlos', function (): void {
    $cliente = FactoryCliente::factory()->make();
    $varios = FactoryCliente::factory()->count(2)->make();

    expect($cliente->exists())->toBeFalse()
        ->and($varios)->toHaveCount(2)
        ->and(FactoryCliente::query()->count())->toBe(0);
});

it('un lote que falla a la mitad no deja registros', function (): void {
    FactoryCliente::factory()->create(['email' => 'repetido@example.com']);

    expect(fn () => FactoryCliente::factory()->count(3)->state(fn (array $v, int $i): array => $i === 2 ? ['email' => 'repetido@example.com'] : [])->create())
        ->toThrow(BootZen\Database\QueryException::class)
        ->and(FactoryCliente::query()->count())->toBe(1);
});

it('explica qué hacer si falta la factory o el lote es vacío', function (): void {
    expect(fn () => FactorySinFactory::factory())->toThrow(LogicException::class, 'php bin/bootzen make:factory FactorySinFactory')
        ->and(fn () => FactoryCliente::factory()->count(0))->toThrow(InvalidArgumentException::class, 'count() necesita 1 o más');
});
