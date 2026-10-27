<?php

declare(strict_types=1);

use BootZen\Core\Container;

final class ContainerTestDependency
{
}

final class ContainerTestService
{
    public function __construct(public ContainerTestDependency $dependency)
    {
    }
}

it('crea una instancia nueva en cada make con bind', function (): void {
    $container = new Container();
    $container->bind(ContainerTestDependency::class);

    expect($container->make(ContainerTestDependency::class))
        ->not->toBe($container->make(ContainerTestDependency::class));
});

it('devuelve la misma instancia con singleton', function (): void {
    $container = new Container();
    $container->singleton(ContainerTestDependency::class);

    expect($container->make(ContainerTestDependency::class))
        ->toBe($container->make(ContainerTestDependency::class));
});

it('resuelve dependencias del constructor por autowiring', function (): void {
    $service = (new Container())->make(ContainerTestService::class);

    expect($service)->toBeInstanceOf(ContainerTestService::class)
        ->and($service->dependency)->toBeInstanceOf(ContainerTestDependency::class);
});

it('devuelve la instancia registrada con instance', function (): void {
    $container = new Container();
    $dependency = new ContainerTestDependency();
    $container->instance('dep', $dependency);

    expect($container->make('dep'))->toBe($dependency);
});

final class ContainerTestController
{
    public function show(ContainerTestDependency $dependency, int $id, ?string $tab = null, bool $activo = false): array
    {
        return [get_debug_type($dependency), $id, $tab, $activo];
    }
}

it('call() resuelve parámetros por nombre, convierte tipos y autowirea clases', function (): void {
    $result = (new Container())->call([ContainerTestController::class, 'show'], ['id' => '7', 'activo' => 'true']);

    expect($result)->toBe([ContainerTestDependency::class, 7, null, true]);
});

it('call() usa las instancias provistas por tipo', function (): void {
    $dependency = new ContainerTestDependency();

    $result = (new Container())->call(
        static fn (ContainerTestDependency $dep): bool => $dep === $dependency,
        [],
        [$dependency]
    );

    expect($result)->toBeTrue();
});

it('call() explica qué parámetro no pudo resolver', function (): void {
    (new Container())->call(static fn (int $id): int => $id);
})->throws(InvalidArgumentException::class, 'No se puede resolver el parámetro [$id]');

it('call() rechaza métodos inexistentes', function (): void {
    (new Container())->call([ContainerTestController::class, 'noExiste']);
})->throws(InvalidArgumentException::class, 'no existe');

interface ContainerTestContract
{
}

abstract class ContainerTestAbstract
{
}

final class ContainerTestUntyped
{
    public function __construct($valor)
    {
    }
}

final class ContainerTestUnion
{
    public function __construct(public ContainerTestDependency|ContainerTestController $dep)
    {
    }
}

final class ContainerTestBuiltin
{
    public function __construct(public int $limite = 10, public string $modo = 'normal')
    {
    }
}

final class ContainerTestRequiredBuiltin
{
    public function __construct(public int $limite)
    {
    }
}

final class ContainerTestOptionalDependency
{
    public function __construct(
        public ?ContainerTestContract $nullable,
        public ?ContainerTestContract $conDefault = null,
    ) {
    }
}

final class ContainerTestNeedsContract
{
    public function __construct(public ContainerTestContract $contrato)
    {
    }
}

it('resuelve bindings con closure y valores ya construidos', function (): void {
    $container = new Container();
    $container->bind('config.valor', static fn (Container $c): string => 'desde closure');
    $container->bind('objeto', new ContainerTestDependency());

    expect($container->make('config.valor'))->toBe('desde closure')
        ->and($container->make('objeto'))->toBeInstanceOf(ContainerTestDependency::class);
});

it('usa los valores por defecto de parámetros escalares', function (): void {
    $built = (new Container())->make(ContainerTestBuiltin::class);

    expect([$built->limite, $built->modo])->toBe([10, 'normal']);
});

it('usa null o el valor por defecto si una dependencia no se puede resolver', function (): void {
    $built = (new Container())->make(ContainerTestOptionalDependency::class);

    expect($built->nullable)->toBeNull()->and($built->conDefault)->toBeNull();
});

it('explica por qué no puede construir una clase', function (string $class, string $message): void {
    (new Container())->make($class);
})->throws(InvalidArgumentException::class)->with([
    'inexistente' => ['App\\NoExiste', 'no existe'],
    'abstracta' => [ContainerTestAbstract::class, 'no es instanciable'],
    'sin tipo' => [ContainerTestUntyped::class, 'sin type hint'],
    'union type' => [ContainerTestUnion::class, 'union type'],
    'escalar obligatorio' => [ContainerTestRequiredBuiltin::class, 'built-in [int]'],
    'interfaz sin binding' => [ContainerTestNeedsContract::class, 'no es instanciable'],
]);

it('informa qué tiene registrado y permite vaciarse', function (): void {
    $container = new Container();
    $container->singleton('servicio', ContainerTestDependency::class);
    $container->instance('valor', 42);

    expect($container->bound('servicio'))->toBeTrue()
        ->and($container->bound('valor'))->toBeTrue()
        ->and($container->bound('otro'))->toBeFalse()
        ->and($container->has(ContainerTestDependency::class))->toBeTrue()
        ->and($container->has('otro'))->toBeFalse()
        ->and(array_keys($container->getBindings()))->toBe(['servicio']);

    $container->flush();

    expect($container->bound('servicio'))->toBeFalse()->and($container->getBindings())->toBe([]);
});

it('vuelve a construir el servicio si se registra de nuevo', function (): void {
    $container = new Container();
    $container->singleton('dep', ContainerTestDependency::class);
    $primero = $container->make('dep');
    $container->singleton('dep', ContainerTestDependency::class);

    expect($container->make('dep'))->not->toBe($primero);
});

it('call() acepta instancias y convierte valores a float', function (): void {
    $container = new Container();

    expect($container->call([new ContainerTestController(), 'show'], ['id' => '3'])[1])->toBe(3)
        ->and($container->call(static fn (float $precio): float => $precio, ['precio' => '9.5']))->toBe(9.5);
});

it('call() no convierte valores no numéricos: el tipo estricto lo rechaza', function (): void {
    (new Container())->call(static fn (int $id): int => $id, ['id' => 'abc']);
})->throws(TypeError::class);
