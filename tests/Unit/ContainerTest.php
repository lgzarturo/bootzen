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
