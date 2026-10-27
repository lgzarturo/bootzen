<?php

/**
 * Container.php
 *
 * Contenedor de dependencias para el framework BootZen.
 * Permite la inyección, resolución y gestión de servicios y dependencias, soportando patrones como IoC y Singleton.
 * Facilita la desacoplación y la escalabilidad de la aplicación, permitiendo la sustitución y el testeo de componentes.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.1.0
 * @since      1.0.0
 *
 */

declare(strict_types=1);

namespace BootZen\Core;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionParameter;

/**
 * Clase Container
 *
 * Implementa un contenedor de inversión de control (IoC) para la gestión de dependencias y servicios.
 * Permite el registro, resolución y cacheo de instancias, soportando servicios singleton y factories.
 *
 * Consideraciones de seguridad: valida la existencia y la instanciabilidad de las clases antes de resolverlas.
 * Consideraciones de rendimiento: cachea instancias singleton y permite factories personalizados.
 *
 */
class Container
{
    /**
     * Servicios y factories registrados en el contenedor.
     *
     * @var array<string, mixed>
     */
    private array $bindings = [];

    /**
     * Instancias singleton compartidas.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Registra un servicio o factory en el contenedor.
     *
     * @param string $abstract Nombre o interfaz abstracta
     * @param mixed $concrete Implementación concreta o Closure
     * @param bool $singleton Si debe ser singleton
     * @return void
     * @example $container->bind(LoggerInterface::class, FileLogger::class);
     */
    public function bind(string $abstract, mixed $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
        ];

        // Elimina instancia existente si se rebindea
        unset($this->instances[$abstract]);
    }

    /**
     * Registra un singleton en el contenedor.
     *
     * @param string $abstract Nombre o interfaz abstracta
     * @param mixed $concrete Implementación concreta o Closure
     * @return void
     * @example $container->singleton(Cache::class, RedisCache::class);
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Registra una instancia existente como compartida.
     *
     * @param string $abstract Nombre o interfaz abstracta
     * @param mixed $instance Instancia concreta
     * @return void
     * @example $container->instance(LoggerInterface::class, $logger);
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resuelve un servicio desde el contenedor.
     *
     * @param string $abstract Nombre o interfaz abstracta
     * @return mixed Instancia resuelta
     * @throws InvalidArgumentException Si la clase no existe o no es instanciable
     * @example $logger = $container->make(LoggerInterface::class);
     */
    public function make(string $abstract): mixed
    {
        // Devuelve instancia existente si es singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // Construye el objeto
        $object = $this->build($concrete);

        // Almacena como singleton si corresponde
        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['singleton']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Obtiene la implementación concreta para un abstracto.
     *
     * @internal
     * @param string $abstract Nombre o interfaz abstracta
     * @return mixed Implementación concreta
     */
    private function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Construye una instancia de la implementación concreta.
     *
     * @internal
     * @param mixed $concrete Implementación concreta
     * @return mixed Instancia creada
     * @throws InvalidArgumentException Si la clase no existe o no es instanciable
     */
    private function build(mixed $concrete): mixed
    {
        // Si es Closure, ejecuta
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        // Si no es string, retorna tal cual
        if (! is_string($concrete)) {
            return $concrete;
        }

        if (! class_exists($concrete)) {
            throw new InvalidArgumentException("La clase objetivo [{$concrete}] no existe.");
        }

        $reflector = new ReflectionClass($concrete);

        // Verifica si la clase es instanciable
        if (! $reflector->isInstantiable()) {
            throw new InvalidArgumentException("El objetivo [{$concrete}] no es instanciable.");
        }

        $constructor = $reflector->getConstructor();

        // Si no tiene constructor, instancia directa
        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters());

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resuelve todas las dependencias para los parámetros dados.
     *
     * @internal
     * @param array<int, ReflectionParameter> $parameters Parámetros del constructor
     * @return array<int, mixed> Dependencias resueltas
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resuelve una sola dependencia.
     *
     * @internal
     * @param ReflectionParameter $parameter Parámetro a resolver
     * @return mixed Dependencia resuelta
     * @throws InvalidArgumentException Si no se puede resolver
     */
    private function resolveDependency(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        // Si no tiene type hint, usa valor por defecto si existe
        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new InvalidArgumentException(
                "No se puede resolver el parámetro [{$parameter->getName()}] sin type hint."
            );
        }

        // Maneja union types (PHP 8+)
        if ($type instanceof \ReflectionUnionType) {
            throw new InvalidArgumentException(
                "No se puede resolver union type para el parámetro [{$parameter->getName()}]."
            );
        }

        $typeName = method_exists($type, 'getName') ? $type->getName() : (string)$type;

        // Maneja tipos built-in
        if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new InvalidArgumentException(
                "No se puede resolver el tipo built-in [{$typeName}] para el parámetro [{$parameter->getName()}]."
            );
        }

        // Intenta resolver desde el contenedor
        try {
            return $this->make($typeName);
        } catch (InvalidArgumentException $e) {
            // Si tiene valor por defecto, úsalo
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            // Si permite null, retorna null
            if ($parameter->allowsNull()) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Ejecuta un callable resolviendo sus parámetros.
     *
     * Orden de resolución de cada parámetro:
     * 1. Por nombre en $parameters (los valores string se convierten a int, float o bool según el tipo).
     * 2. Por tipo entre los objetos de $instances (por ejemplo, el Request actual).
     * 3. Por tipo desde el contenedor (autowiring).
     * 4. Valor por defecto o null si el parámetro lo permite.
     *
     * @param callable|array{0: object|string, 1: string}|string $callable Closure, [Clase, 'metodo'] o clase invocable
     * @param array<string, mixed> $parameters Valores por nombre de parámetro
     * @param array<int, object> $instances Objetos disponibles por tipo
     * @return mixed Resultado del callable
     * @throws InvalidArgumentException Si un parámetro no se puede resolver o el callable no es válido
     * @example $container->call([TaskController::class, 'show'], ['id' => '42'], [$request]);
     */
    public function call(callable|array|string $callable, array $parameters = [], array $instances = []): mixed
    {
        if (is_string($callable) && ! is_callable($callable)) {
            $callable = [$callable, '__invoke'];
        }

        if (is_array($callable)) {
            [$target, $method] = $callable;
            $object = is_string($target) ? $this->make($target) : $target;

            if (! is_object($object) || ! method_exists($object, $method)) {
                $class = is_object($object) ? $object::class : (string) $target;

                throw new InvalidArgumentException("El método [{$class}::{$method}] no existe.");
            }

            $reflection = new \ReflectionMethod($object, $method);

            return $reflection->invokeArgs($object, $this->resolveCallParameters($reflection, $parameters, $instances));
        }

        $reflection = new \ReflectionFunction(Closure::fromCallable($callable));

        return $reflection->invokeArgs($this->resolveCallParameters($reflection, $parameters, $instances));
    }

    /**
     * Resuelve los argumentos de una función o método para call().
     *
     * @param array<string, mixed> $parameters
     * @param array<int, object> $instances
     * @return array<int, mixed>
     */
    private function resolveCallParameters(
        \ReflectionFunctionAbstract $function,
        array $parameters,
        array $instances
    ): array {
        $arguments = [];

        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $isClass = $type instanceof \ReflectionNamedType && ! $type->isBuiltin();

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $this->coerce($parameters[$name], $type);

                continue;
            }

            if ($isClass && $typeName !== null) {
                foreach ($instances as $instance) {
                    if ($instance instanceof $typeName) {
                        $arguments[] = $instance;

                        continue 2;
                    }
                }

                $arguments[] = $this->resolveDependency($parameter);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $arguments[] = null;
            } else {
                throw new InvalidArgumentException(
                    "No se puede resolver el parámetro [\${$name}] de [{$function->getName()}]. "
                    . 'Los parámetros de ruta se inyectan por nombre: usa el mismo nombre que en {placeholder}.'
                );
            }
        }

        return $arguments;
    }

    /**
     * Convierte valores string (como los parámetros de ruta) al tipo escalar declarado.
     */
    private function coerce(mixed $value, ?\ReflectionType $type): mixed
    {
        if (! is_string($value) || ! $type instanceof \ReflectionNamedType || ! $type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value,
            default => $value,
        };
    }

    /**
     * Verifica si un servicio está registrado en el contenedor.
     *
     * @param string $id Identificador del servicio
     * @return bool true si está registrado
     */
    public function has(string $id): bool
    {
        // Ajustar esta lógica según el mecanismo de almacenamiento
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    /**
     * Verifica si un servicio está vinculado.
     *
     * @param string $abstract Nombre o interfaz abstracta
     * @return bool true si está vinculado
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Obtiene todos los bindings registrados.
     *
     * @return array<string, mixed> Bindings actuales
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Limpia el contenedor (elimina todos los servicios y singletons).
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
    }
}
