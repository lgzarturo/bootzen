<?php

/**
 * Component.php
 *
 * Clase base abstracta para componentes visuales reutilizables en el framework BootZen.
 * Proporciona un sistema flexible para la composición, renderizado, cacheo y gestión de slots y propiedades.
 * Permite la creación de interfaces reactivas y seguras, soportando patrones de diseño como Composición,
 * Template Method y Decorator.
 *
 * @package    BootZen\Core
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @version    1.0.7
 * @since      1.0.0
 *
 * @see        BootZen\Core\Cache
 */

declare(strict_types=1);

namespace BootZen\Core;

use BootZen\Core\Cache as Cache;
use Closure;

/**
 * Clase abstracta Component
 *
 * Define la estructura y comportamiento base para todos los componentes visuales del framework BootZen.
 * Permite la composición jerárquica, el uso de slots, cacheo, hidratación y renderizado seguro de HTML.
 *
 * Consideraciones de seguridad: sanitiza propiedades para prevenir XSS y permite marcar datos como HTML crudo.
 * Consideraciones de rendimiento: soporta cacheo de salidas y renderizado condicional.
 *
 */
abstract class Component
{
    /**
     * Propiedades del componente.
     *
     * @var array<string, mixed>
     */
    protected array $props = [];

    /**
     * Slots del componente (contenido dinámico o subcomponentes).
     *
     * @var array<string, mixed|Closure|Component>
     */
    protected array $slots = [];

    /**
     * Componentes hijos.
     *
     * @var array<int, Component>
     */
    protected array $children = [];

    /**
     * Clave de caché personalizada para el componente.
     *
     * @var string|null
     */
    protected ?string $cacheKey = null;

    /**
     * Tiempo de vida de la caché en segundos.
     *
     * @var int
     */
    protected int $cacheTtl = 3600;

    /**
     * Indica si el componente debe hidratarse en el cliente.
     *
     * @var bool
     */
    protected bool $hydrate = false;

    /**
     * Datos adicionales para la hidratación en cliente.
     *
     * @var array<string, mixed>
     */
    protected array $clientData = [];

    /**
     * Instancia de caché compartida entre componentes.
     *
     * @var Cache|null
     */
    protected static ?Cache $cache = null;

    /**
     * Constructor del componente.
     *
     * @param array<string, mixed> $props Propiedades iniciales
     * @param array<string, mixed|\Closure|Component> $slots Slots iniciales
     */
    public function __construct(array $props = [], array $slots = [])
    {
        $this->props = $this->sanitizeProps($props);
        $this->slots = $slots;
        $this->init();
    }

    /**
     * Inicializa el componente. Sobrescribir en clases hijas para lógica personalizada.
     *
     */
    protected function init(): void
    {
        // Sobrescribir en clases hijas para lógica de inicialización
    }

    /**
     * Establece la instancia de caché global para todos los componentes.
     *
     * @param Cache $cache Instancia de caché
     */
    public static function setCache(Cache $cache): void
    {
        self::$cache = $cache;
    }

    /**
     * Renderiza el componente y aplica cacheo, hooks y datos de hidratación.
     *
     * @return string HTML renderizado
     * @example echo $componente->render();
     */
    public function render(): string
    {
        // Verifica caché primero
        if ($this->cacheKey && self::$cache) {
            $cached = self::$cache->get($this->buildCacheKey());
            if ($cached !== null) {
                return $cached;
            }
        }

        // Ejecuta hook antes de renderizar
        $this->beforeRender();

        // Renderiza el componente
        $output = $this->template();

        // Ejecuta hook después de renderizar
        $this->afterRender();

        // Cachea la salida si corresponde
        if ($this->cacheKey && self::$cache) {
            self::$cache->set($this->buildCacheKey(), $output, $this->cacheTtl);
        }

        // Añade datos de hidratación si es necesario
        if ($this->hydrate) {
            $output = $this->addHydrationData($output);
        }

        return $output;
    }

    /**
     * Método abstracto que define la plantilla del componente.
     * Debe ser implementado por las clases hijas.
     *
     * @return string HTML de la plantilla
     */
    abstract protected function template(): string;

    /**
     * Hook ejecutado antes del renderizado. Sobrescribir en clases hijas.
     *
     */
    protected function beforeRender(): void
    {
        // Sobrescribir en clases hijas
    }

    /**
     * Hook ejecutado después del renderizado. Sobrescribir en clases hijas.
     *
     */
    protected function afterRender(): void
    {
        // Sobrescribir en clases hijas
    }

    /**
     * Habilita el cacheo para este componente.
     *
     * @param string $key Clave de caché
     * @param int $ttl Tiempo de vida en segundos
     * @return $this
     * @example $componente->cache('mi_componente', 600);
     */
    public function cache(string $key, int $ttl = 3600): self
    {
        $this->cacheKey = $key;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Habilita la hidratación en cliente para este componente.
     *
     * @param array<string, mixed> $data Datos adicionales para el cliente
     * @return $this
     * @example $componente->hydrate(['foo' => 'bar']);
     */
    public function hydrate(array $data = []): self
    {
        $this->hydrate = true;
        $this->clientData = array_merge($this->clientData, $data);

        return $this;
    }

    /**
     * Añade un componente hijo.
     *
     * @param Component $child Componente hijo
     * @return $this
     */
    public function addChild(Component $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * Obtiene el valor de una propiedad.
     *
     * @param string $key Nombre de la propiedad
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor de la propiedad
     */
    protected function prop(string $key, mixed $default = null): mixed
    {
        return $this->props[$key] ?? $default;
    }

    /**
     * Obtiene el contenido de un slot.
     *
     * @param string $name Nombre del slot (por defecto 'default')
     * @return string Contenido renderizado del slot
     */
    protected function slot(string $name = 'default'): string
    {
        if (! isset($this->slots[$name])) {
            return '';
        }

        $slot = $this->slots[$name];

        if ($slot instanceof Closure) {
            return $slot($this->props);
        }

        if ($slot instanceof Component) {
            return $slot->render();
        }

        return (string) $slot;
    }

    /**
     * Verifica si existe un slot.
     *
     * @param string $name Nombre del slot
     * @return bool true si existe
     */
    protected function hasSlot(string $name = 'default'): bool
    {
        return isset($this->slots[$name]);
    }

    /**
     * Renderiza los componentes hijos.
     *
     * @return string HTML concatenado de los hijos
     */
    protected function renderChildren(): string
    {
        return implode('', array_map(fn ($child) => $child->render(), $this->children));
    }

    /**
     * Escapa la salida HTML.
     *
     * @param mixed $value Valor a escapar
     * @return string Valor escapado
     */
    protected function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Salida cruda (sin escapar).
     *
     * @param mixed $value Valor a mostrar
     * @return string Valor sin escapar
     */
    protected function raw(mixed $value): string
    {
        return (string) $value;
    }

    /**
     * Helper para clases condicionales.
     *
     * @param array<int|string, mixed> $classes Array de nombres de clase o condiciones
     * @return string Clases concatenadas
     * @example $this->classNames(['active' => $isActive, 'foo']);
     */
    protected function classNames(array $classes): string
    {
        $result = [];

        foreach ($classes as $class => $condition) {
            if (is_numeric($class)) {
                // Nombre de clase simple
                $result[] = $condition;
            } elseif ($condition) {
                // Clase condicional
                $result[] = $class;
            }
        }

        return implode(' ', array_filter($result));
    }

    /**
     * Incluye otro componente.
     *
     * @param class-string<Component> $componentClass Clase del componente a incluir
     * @param array<string, mixed> $props Propiedades a pasar
     * @param array<string, mixed>|\Closure|Component $slots Slots a pasar
     * @return string HTML renderizado del componente
     * @throws \InvalidArgumentException Si la clase no existe
     * @throws \RuntimeException Si ocurre un error al renderizar
     * @example $this->component(Button::class, ['label' => 'OK']);
     */
    protected function component(
        string $componentClass,
        array $props = [],
        array|\Closure|Component $slots = []
    ): string {
        if (! class_exists($componentClass)) {
            throw new \InvalidArgumentException("Component class {$componentClass} does not exist");
        }

        try {
            // Hereda configuración de caché e hidratación
            $component = new $componentClass($props, $slots);

            return $component->render();
        } catch (\Throwable $e) {
            // Lanza excepción si falla el renderizado
            throw new \RuntimeException("Error renderizando componente {$componentClass}: " . $e->getMessage());
        }
    }

    /**
     * Crea un fragmento (múltiples componentes o HTML).
     *
     * @param array<int, Component|string> $components Array de componentes o HTML
     * @return string HTML concatenado
     * @example $this->fragment([$comp1, $comp2, '<hr>']);
     */
    protected function fragment(array $components): string
    {
        $output = '';

        foreach ($components as $component) {
            if ($component instanceof Component) {
                $output .= $component->render();
            } elseif (is_string($component)) {
                $output .= $component;
            }
        }

        return $output;
    }

    /**
     * Renderizado condicional.
     *
     * @param bool $condition Condición a evaluar
     * @param mixed $content Contenido si es verdadero
     * @param mixed $fallback Contenido si es falso
     * @return string HTML resultante
     * @example $this->when($isVisible, 'Visible', 'Oculto');
     */
    protected function when(bool $condition, mixed $content, mixed $fallback = ''): string
    {
        if (! $condition) {
            return $this->renderContent($fallback);
        }

        return $this->renderContent($content);
    }

    /**
     * Renderizado en bucle.
     *
     * @param array<int, mixed> $items Elementos a iterar
     * @param Closure(mixed, int): mixed $callback Callback para cada elemento
     * @return string HTML concatenado
     * @example $this->each($items, fn($item) => $item);
     */
    protected function each(array $items, Closure $callback): string
    {
        $output = '';

        foreach ($items as $index => $item) {
            $result = $callback($item, $index);
            $output .= $this->renderContent($result);
        }

        return $output;
    }

    /**
     * Helper para renderizar contenido (interno).
     *
     * @internal
     * @param mixed $content Contenido a renderizar
     * @return string HTML resultante
     */
    private function renderContent(mixed $content): string
    {
        if ($content instanceof Component) {
            return $content->render();
        }

        if ($content instanceof Closure) {
            return $this->renderContent($content());
        }

        return (string) $content;
    }

    /**
     * Construye la clave de caché única para el componente.
     *
     * @internal
     * @return string Clave de caché
     */
    private function buildCacheKey(): string
    {
        $componentName = static::class;
        $propsHash = md5(serialize($this->props));

        return "component:{$componentName}:{$this->cacheKey}:{$propsHash}";
    }

    /**
     * Sanitiza las propiedades para prevenir XSS.
     *
     * @internal
     * @param array<string, mixed> $props Propiedades a sanitizar
     * @return array<string, mixed> Propiedades sanitizadas
     */
    private function sanitizeProps(array $props): array
    {
        $sanitized = [];

        foreach ($props as $key => $value) {
            if (is_string($value)) {
                // No sanitizar si es HTML crudo
                if (str_starts_with($key, 'raw_')) {
                    $sanitized[$key] = $value;
                } else {
                    $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            } elseif (is_array($value)) {
                // Sanitiza recursivamente arrays
                $sanitized[$key] = $this->sanitizeProps($value);
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $sanitized[$key] = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                } else {
                    $sanitized[$key] = $value;
                }
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Añade datos de hidratación al HTML de salida.
     *
     * @internal
     * @param string $output HTML original
     * @return string HTML con datos de hidratación
     */
    private function addHydrationData(string $output): string
    {
        $hydrationData = [
            'component' => static::class,
            'props' => $this->props,
            'data' => $this->clientData,
        ];

        $json = json_encode($hydrationData);
        $dataAttribute = 'data-hydrate="' . htmlspecialchars($json !== false ? $json : '', ENT_QUOTES) . '"';

        // Intenta añadir al primer elemento HTML
        if (preg_match('/<([a-zA-Z][a-zA-Z0-9]*)[^>]*>/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = (int) $matches[0][1];
            $tagEnd = strpos($output, '>', $offset) + 1;
            $before = substr($output, 0, $tagEnd - 1);
            $after = substr($output, $tagEnd - 1);

            return $before . ' ' . $dataAttribute . $after;
        }

        // Fallback: envolver en div
        return "<div {$dataAttribute}>{$output}</div>";
    }

    /**
     * Método de factoría estático para instanciar componentes.
     *
     * @param array<string, mixed> $props Propiedades iniciales
     * @param array<string, mixed> $slots Slots iniciales
     * @return static Instancia del componente
     * @throws \LogicException Si se intenta instanciar una clase abstracta
     * @example Button::make(['label' => 'OK']);
     */
    public static function make(array $props = [], array $slots = []): static
    {
        $reflection = new \ReflectionClass(static::class);
        if ($reflection->isAbstract()) {
            throw new \LogicException('No se puede instanciar una clase abstracta ' . static::class . ' vía make().');
        }

        /** @var static */
        $instance = $reflection->newInstance($props, $slots);

        return $instance;
    }

    /**
     * Acceso mágico a propiedades.
     *
     * @param string $name Nombre de la propiedad
     * @return mixed Valor de la propiedad o null
     */
    public function __get(string $name): mixed
    {
        return $this->props[$name] ?? null;
    }

    /**
     * Verifica mágicamente si existe una propiedad.
     *
     * @param string $name Nombre de la propiedad
     * @return bool true si existe
     */
    public function __isset(string $name): bool
    {
        return isset($this->props[$name]);
    }

    /**
     * Convierte el componente a string (renderiza).
     *
     * @return string HTML renderizado o mensaje de error
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            error_log("Component render error: " . $e->getMessage());

            return "<!-- Component render error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }
}
