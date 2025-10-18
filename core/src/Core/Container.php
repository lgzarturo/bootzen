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
 * @version    1.0.7
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
