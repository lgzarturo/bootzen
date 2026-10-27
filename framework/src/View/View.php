<?php

/**
 * View.php
 *
 * Motor de vistas de BootZen: resuelve nombres ('tareas.index'), compila con ViewCompiler,
 * guarda lo compilado en caché (se invalida por fecha de modificación) y renderiza vistas
 * y componentes <x-*> con slots y atributos.
 *
 * @package    BootZen\View
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\View;

use BootZen\Core\Container;
use BootZen\Core\HttpException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Stringable;
use Throwable;

/**
 * Entorno de vistas.
 *
 * @example $view->render('tareas.index', ['tareas' => $tareas]);
 */
final class View
{
    /**
     * Datos disponibles en todas las vistas y componentes.
     *
     * @var array<string, mixed>
     */
    private array $shared = ['errors' => []];

    /**
     * Componentes en construcción: nombre, atributos y slots capturados.
     *
     * @var list<array{name: string, attributes: array<string, mixed>, slots: array<string, HtmlString>, level: int}>
     */
    private array $components = [];

    /**
     * Slots abiertos dentro del componente actual.
     *
     * @var list<string>
     */
    private array $slots = [];

    private readonly ViewCompiler $compiler;

    /**
     * @param string $viewsPath Directorio de vistas (resources/views)
     * @param string $cachePath Directorio de vistas compiladas (storage/cache/views)
     * @param Container|null $container Para resolver dependencias de componentes con clase
     * @param string $componentNamespace Namespace de los componentes con clase
     */
    public function __construct(
        private readonly string $viewsPath,
        private readonly string $cachePath,
        private readonly ?Container $container = null,
        private readonly string $componentNamespace = 'App\\View\\Components\\'
    ) {
        $this->compiler = new ViewCompiler();
    }

    /**
     * Renderiza una vista por nombre.
     *
     * @param string $name Nombre con puntos: 'tareas.index' → resources/views/tareas/index.php
     * @param array<string, mixed> $data Variables para la vista
     * @throws ViewException Si la vista no existe o falla al compilar o renderizar
     */
    public function render(string $name, array $data = []): string
    {
        return $this->evaluate($this->path($name), array_merge($this->shared, $data));
    }

    /**
     * Indica si existe una vista.
     */
    public function exists(string $name): bool
    {
        return is_file($this->file($name));
    }

    /**
     * Comparte datos con todas las vistas.
     *
     * @param string|array<string, mixed> $key Nombre o mapa nombre => valor
     */
    public function share(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $name => $item) {
            $this->shared[$name] = $item;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Ruta del archivo de una vista.
     *
     * @throws ViewException Si no existe
     */
    public function path(string $name): string
    {
        $file = $this->file($name);

        if (! is_file($file)) {
            throw new ViewException("La vista [{$name}] no existe. Crea el archivo {$this->relative($file)}.", $file);
        }

        return $file;
    }

    /**
     * Compila todas las vistas (para producción). Devuelve cuántas compiló.
     *
     * @throws ViewException Si alguna vista tiene errores de sintaxis
     */
    public function compileAll(): int
    {
        $count = 0;

        if (! is_dir($this->viewsPath)) {
            return 0;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->viewsPath, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->compileFile($file->getPathname());
                $count++;
            }
        }

        return $count;
    }

    /**
     * Borra las vistas compiladas. Devuelve cuántos archivos borró.
     */
    public function clearCache(): int
    {
        $files = glob($this->cachePath . '/*.php') ?: [];

        foreach ($files as $file) {
            @unlink($file);
        }

        return count($files);
    }

    /**
     * Escapa un valor para {{ }}. HtmlString y los atributos de componente se imprimen tal cual.
     *
     * @internal Lo usan las vistas compiladas
     * @throws ViewException Si el valor no se puede imprimir (arrays u objetos sin __toString)
     */
    public function escape(mixed $value): string
    {
        if ($value instanceof HtmlString || $value instanceof ComponentAttributeBag) {
            return (string) $value;
        }

        return htmlspecialchars($this->toText($value, '{{ }}'), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Convierte un valor interpolado en un atributo de componente a texto (sin escapar: lo escapa la bolsa).
     *
     * @internal Lo usan las vistas compiladas
     */
    public function toAttribute(mixed $value): string
    {
        return $this->toText($value, 'un atributo');
    }

    /**
     * @internal Lo usan las vistas compiladas
     * @param array<string, mixed> $attributes
     */
    public function startComponent(string $name, array $attributes): void
    {
        $this->components[] = ['name' => $name, 'attributes' => $attributes, 'slots' => [], 'level' => ob_get_level()];
        ob_start();
    }

    /**
     * @internal Lo usan las vistas compiladas
     * @throws ViewException Si se abre un slot fuera de un componente
     */
    public function startSlot(string $name): void
    {
        if ($this->components === []) {
            throw new ViewException("<x-slot:{$name}> solo se puede usar dentro de un componente.");
        }

        $this->slots[] = $name;
        ob_start();
    }

    /**
     * @internal Lo usan las vistas compiladas
     * @throws ViewException Si no hay un slot abierto
     */
    public function endSlot(): void
    {
        $name = array_pop($this->slots);

        if ($name === null || $this->components === []) {
            throw new ViewException('</x-slot> sin un <x-slot:nombre> abierto.');
        }

        $this->components[array_key_last($this->components)]['slots'][$name] = new HtmlString(trim((string) ob_get_clean()));
    }

    /**
     * Cierra el componente actual y devuelve su HTML.
     *
     * @internal Lo usan las vistas compiladas
     * @throws ViewException Si el componente no existe o falla al renderizar
     */
    public function renderComponent(): string
    {
        $frame = array_pop($this->components);

        if ($frame === null) {
            throw new ViewException('Cierre de componente </x-...> sin apertura.');
        }

        $default = new HtmlString(trim((string) ob_get_clean()));
        $file = $this->file('components.' . $frame['name']);

        if (! is_file($file)) {
            throw new ViewException(
                "El componente <x-{$frame['name']}> no existe. Crea el archivo {$this->relative($file)}.",
                $file
            );
        }

        $attributes = new ComponentAttributeBag($frame['attributes']);
        $data = [];
        $class = $this->componentClass($frame['name']);

        if ($class !== null) {
            [$data, $attributes] = $this->buildComponent($class, $frame['name'], $attributes);
        }

        return $this->evaluate($file, array_merge(
            $this->shared,
            $data,
            $frame['slots'],
            ['attributes' => $attributes, 'slot' => $default]
        ));
    }

    /**
     * Separa las props declaradas con @props del resto de atributos.
     *
     * @internal Lo usan las vistas compiladas
     * @param array<int|string, mixed> $definition Lista de nombres (obligatorias) o nombre => valor por defecto
     * @return array{0: array<string, mixed>, 1: ComponentAttributeBag}
     * @throws ViewException Si se usa fuera de un componente o falta una prop obligatoria
     */
    public function resolveProps(?ComponentAttributeBag $attributes, array $definition): array
    {
        if ($attributes === null) {
            throw new ViewException('@props solo se puede usar en la vista de un componente.');
        }

        $props = [];
        $used = [];

        foreach ($definition as $key => $default) {
            $name = is_int($key) ? (string) $default : $key;
            $attribute = $this->findAttribute($attributes, $name);

            if ($attribute !== null) {
                $props[$name] = $attributes->get($attribute);
                $used[] = $attribute;
            } elseif (is_int($key)) {
                throw new ViewException("Falta la prop obligatoria [{$name}] del componente.");
            } else {
                $props[$name] = $default;
            }
        }

        return [$props, $attributes->except($used)];
    }

    /**
     * Campo oculto _method para enviar PUT, PATCH o DELETE desde un formulario.
     *
     * @internal Lo usan las vistas compiladas
     * @throws ViewException Si el método no se puede simular
     */
    public function methodField(string $method): string
    {
        $method = strtoupper($method);

        if (! in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            throw new ViewException("@method solo acepta PUT, PATCH o DELETE; se recibió [{$method}].");
        }

        return '<input type="hidden" name="_method" value="' . $method . '">';
    }

    /**
     * @internal Lo usan las vistas compiladas
     */
    public function hasError(string $field): bool
    {
        return $this->firstError($field) !== '';
    }

    /**
     * Primer mensaje de error de un campo (de los datos compartidos 'errors').
     *
     * @internal Lo usan las vistas compiladas
     */
    public function firstError(string $field): string
    {
        $errors = $this->shared['errors'] ?? [];
        $messages = is_array($errors) ? ($errors[$field] ?? null) : null;

        if (is_array($messages)) {
            $messages = reset($messages);
        }

        return is_string($messages) ? $messages : '';
    }

    /**
     * Compila (si hace falta) y ejecuta una vista con sus datos.
     *
     * @param array<string, mixed> $data
     * @throws ViewException
     */
    private function evaluate(string $file, array $data): string
    {
        $compiled = $this->compileFile($file);
        $level = ob_get_level();
        $components = count($this->components);
        $slots = count($this->slots);
        ob_start();

        try {
            (static function (string $__path, array $__data, View $__env): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($compiled, $data, $this);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            $this->components = array_slice($this->components, 0, $components);
            $this->slots = array_slice($this->slots, 0, $slots);

            throw $this->wrap($e, $file, $compiled);
        }

        if (count($this->components) !== $components) {
            $name = $this->components[$components]['name'] ?? '?';
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            $this->components = array_slice($this->components, 0, $components);

            throw new ViewException("El componente <x-{$name}> no se cerró en {$this->relative($file)}.", $file);
        }

        return (string) ob_get_clean();
    }

    /**
     * Devuelve la ruta compilada de una vista, compilándola si cambió.
     *
     * @throws ViewException Si no se puede leer, compilar o escribir
     */
    private function compileFile(string $file): string
    {
        // Nombre según la ruta relativa al proyecto: las vistas precompiladas en deploy:build sirven en el servidor
        $compiled = $this->cachePath . '/' . sha1($this->relative($file)) . '.php';

        if (is_file($compiled) && filemtime($compiled) > filemtime($file)) {
            return $compiled;
        }

        $source = file_get_contents($file);
        if ($source === false) {
            throw new ViewException("No se pudo leer la vista {$this->relative($file)}.", $file);
        }

        try {
            $compiledSource = $this->compiler->compile($source, $this->relative($file));
        } catch (ViewException $e) {
            throw new ViewException($e->getMessage(), $file, $e->getViewLine(), $e);
        }

        // El comentario va en la línea 1 sin salto propio: no desplaza las líneas de la vista original
        $php = "<?php /** VISTA: {$this->relative($file)} */ ?>" . $compiledSource;

        if (! is_dir($this->cachePath) && ! @mkdir($this->cachePath, 0775, true) && ! is_dir($this->cachePath)) {
            throw new ViewException("No se pudo crear el directorio de vistas compiladas {$this->cachePath}.");
        }

        $temporary = $compiled . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $php, LOCK_EX) === false || ! rename($temporary, $compiled)) {
            @unlink($temporary);

            throw new ViewException("No se pudo escribir la vista compilada en {$this->cachePath}.");
        }

        // Garantiza que la vista compilada quede más nueva que el original aunque se escriban en el mismo segundo
        touch($compiled, max(time(), (int) filemtime($file) + 1));

        return $compiled;
    }

    /**
     * Convierte un error ocurrido en una vista compilada en un ViewException con la línea original.
     */
    private function wrap(Throwable $e, string $file, string $compiled): Throwable
    {
        if ($e instanceof ViewException || $e instanceof HttpException) {
            return $e;
        }

        $line = $e->getFile() === $compiled ? $e->getLine() : 0;

        foreach ($e->getTrace() as $frame) {
            if ($line === 0 && ($frame['file'] ?? null) === $compiled) {
                $line = (int) ($frame['line'] ?? 0);
            }
        }

        $location = $this->relative($file) . ($line > 0 ? ':' . $line : '');

        return new ViewException("{$e->getMessage()} (en la vista {$location})", $file, $line, $e);
    }

    /**
     * Instancia un componente con clase llenando el constructor con atributos del mismo nombre.
     *
     * @param class-string $class
     * @return array{0: array<string, mixed>, 1: ComponentAttributeBag}
     * @throws ViewException Si la clase no extiende Component o falta un atributo obligatorio
     */
    private function buildComponent(string $class, string $name, ComponentAttributeBag $attributes): array
    {
        $reflection = new ReflectionClass($class);

        if (! $reflection->isSubclassOf(Component::class)) {
            throw new ViewException("La clase {$class} del componente <x-{$name}> debe extender " . Component::class . '.');
        }

        $arguments = [];
        $used = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $attribute = $this->findAttribute($attributes, $parameter->getName());
            $type = $parameter->getType();

            if ($attribute !== null) {
                $arguments[] = $attributes->get($attribute);
                $used[] = $attribute;
            } elseif ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && $this->container !== null) {
                $arguments[] = $this->container->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $arguments[] = null;
            } else {
                throw new ViewException("Falta el atributo [{$parameter->getName()}] del componente <x-{$name}>.");
            }
        }

        /** @var Component $component */
        $component = $reflection->newInstanceArgs($arguments);
        $data = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (! $property->isStatic()) {
                $data[$property->getName()] = $property->getValue($component);
            }
        }

        return [array_merge($data, $component->data()), $attributes->except($used)];
    }

    /**
     * Clase del componente si existe: 'layouts.app' → App\View\Components\Layouts\App.
     *
     * @return class-string|null
     */
    private function componentClass(string $name): ?string
    {
        $segments = array_map(
            static fn (string $segment): string => str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $segment))),
            explode('.', $name)
        );

        $class = $this->componentNamespace . implode('\\', $segments);

        return class_exists($class) ? $class : null;
    }

    /**
     * Busca un atributo por nombre o por su variante kebab-case (tituloPrincipal → titulo-principal).
     */
    private function findAttribute(ComponentAttributeBag $attributes, string $name): ?string
    {
        if ($attributes->has($name)) {
            return $name;
        }

        $kebab = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));

        return $attributes->has($kebab) ? $kebab : null;
    }

    private function file(string $name): string
    {
        return rtrim($this->viewsPath, '/') . '/' . str_replace('.', '/', $name) . '.php';
    }

    /**
     * @throws ViewException Si el valor no se puede convertir a texto
     */
    private function toText(mixed $value, string $where): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new ViewException(sprintf(
            'No se puede imprimir un valor de tipo %s en %s. Usa @foreach o json_encode().',
            get_debug_type($value),
            $where
        ));
    }

    private function relative(string $file): string
    {
        $base = dirname(rtrim($this->viewsPath, '/'), 2);

        return str_starts_with($file, $base . '/') ? substr($file, strlen($base) + 1) : $file;
    }
}
