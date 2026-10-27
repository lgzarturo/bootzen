<?php

declare(strict_types=1);

use BootZen\Core\Container;
use BootZen\Core\MiddlewareInterface;
use BootZen\Core\Pipeline;
use BootZen\Core\Request;
use BootZen\Core\Response;

final class PipelineTestOrder implements MiddlewareInterface
{
    public function __construct(private readonly string $name = 'x')
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        return $response->withBody($this->name . '(' . $response->getBody() . ')');
    }
}

final class PipelineTestShortCircuit implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return Response::html('cortado', 403);
    }
}

it('ejecuta el middleware en orden alrededor del destino', function (): void {
    $pipeline = new Pipeline(new Container());

    $response = $pipeline->run(
        [new PipelineTestOrder('a'), new PipelineTestOrder('b')],
        new Request('GET', '/'),
        static fn (): Response => Response::html('destino')
    );

    expect($response->getBody())->toBe('a(b(destino))');
});

it('permite cortar la cadena', function (): void {
    $response = (new Pipeline(new Container()))->run(
        [PipelineTestShortCircuit::class, new PipelineTestOrder('nunca')],
        new Request('GET', '/'),
        static fn (): Response => Response::html('destino')
    );

    expect($response->getStatusCode())->toBe(403)->and($response->getBody())->toBe('cortado');
});

it('expande grupos anidados y alias', function (): void {
    $pipeline = new Pipeline(new Container());
    $pipeline->alias('orden', PipelineTestOrder::class);
    $pipeline->group('base', ['orden']);
    $pipeline->group('web', ['base', PipelineTestShortCircuit::class]);

    expect($pipeline->expand(['web']))->toBe([PipelineTestOrder::class, PipelineTestShortCircuit::class]);
});

it('detecta grupos que se incluyen a sí mismos', function (): void {
    $pipeline = new Pipeline(new Container());
    $pipeline->group('a', ['b']);
    $pipeline->group('b', ['a']);

    $pipeline->expand(['a']);
})->throws(InvalidArgumentException::class, 'se incluye a sí mismo');

it('rechaza middleware desconocido o que no implementa la interfaz', function (string $middleware, string $message): void {
    (new Pipeline(new Container()))->run([$middleware], new Request('GET', '/'), static fn (): Response => Response::html(''));
})->throws(InvalidArgumentException::class)->with([
    'desconocido' => ['auth', 'desconocido'],
    'sin interfaz' => [stdClass::class, 'debe implementar MiddlewareInterface'],
]);
