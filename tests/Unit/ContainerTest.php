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
