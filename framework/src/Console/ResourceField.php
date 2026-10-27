<?php

/**
 * ResourceField.php
 *
 * Campo de make:resource ('titulo:string', 'notas:text:nullable'): sabe su columna SQL, su regla de
 * validación, su cast, su tipo en PHPDoc, su campo de formulario y cómo mostrarse.
 *
 * @package    BootZen\Console
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Console;

use BootZen\Support\Str;
use InvalidArgumentException;

/**
 * Tipos: string, text, email, int, float, bool, date y datetime. Modificador opcional: nullable.
 */
final class ResourceField
{
    public const TYPES = ['string', 'text', 'email', 'int', 'float', 'bool', 'date', 'datetime'];

    private const ALIASES = ['integer' => 'int', 'decimal' => 'float', 'boolean' => 'bool'];

    private const RESERVED = ['id', 'created_at', 'updated_at'];

    private function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable
    ) {
    }

    /**
     * @throws InvalidArgumentException Si el nombre, el tipo o el modificador no son válidos
     */
    public static function parse(string $definition): self
    {
        $parts = explode(':', $definition);
        $name = $parts[0];
        $type = self::ALIASES[$parts[1] ?? 'string'] ?? ($parts[1] ?? 'string');
        $modifier = $parts[2] ?? null;

        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Campo inválido [{$name}]: usa minúsculas, números y guion bajo (por ejemplo fecha_limite).");
        }

        if (in_array($name, self::RESERVED, true)) {
            throw new InvalidArgumentException("El campo [{$name}] ya lo crea el generador; quítalo de la lista.");
        }

        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Tipo [{$type}] desconocido en {$definition}. Usa: " . implode(', ', self::TYPES) . '.');
        }

        if ($modifier !== null && $modifier !== 'nullable') {
            throw new InvalidArgumentException("Modificador [{$modifier}] desconocido en {$definition}. Solo existe nullable.");
        }

        // Un booleano siempre tiene valor (0 por defecto)
        return new self($name, $type, $modifier === 'nullable' && $type !== 'bool');
    }

    public function label(): string
    {
        return Str::headline($this->name);
    }

    /**
     * Definición de la columna para CREATE TABLE.
     */
    public function column(string $driver): string
    {
        $mysql = $driver === 'mysql';
        $sql = match ($this->type) {
            'string', 'email' => $mysql ? 'VARCHAR(255)' : 'TEXT',
            'text' => 'TEXT',
            'int' => $mysql ? 'INT' : 'INTEGER',
            'float' => $mysql ? 'DOUBLE' : 'REAL',
            'bool' => $mysql ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
            'date' => $mysql ? 'DATE' : 'TEXT',
            default => $mysql ? 'DATETIME' : 'TEXT',
        };

        if ($this->type !== 'bool') {
            $sql .= $this->nullable ? ' NULL' : ' NOT NULL';
        }

        return $this->name . ' ' . $sql;
    }

    /**
     * Reglas de validación ('required|string|max:255').
     */
    public function rules(): string
    {
        $rules = match ($this->type) {
            'string' => 'string|max:255',
            'text' => 'string',
            'email' => 'email|max:255',
            'int' => 'integer',
            'float' => 'numeric',
            'bool' => 'boolean',
            default => 'date',
        };

        if ($this->type === 'bool') {
            return $rules;
        }

        return ($this->nullable ? 'nullable|' : 'required|') . $rules;
    }

    /**
     * Cast del modelo, o null si no necesita.
     */
    public function cast(): ?string
    {
        return match ($this->type) {
            'int' => 'int',
            'float' => 'float',
            'bool' => 'bool',
            'date' => 'date',
            'datetime' => 'datetime',
            default => null,
        };
    }

    /**
     * Tipo para el @property del modelo.
     */
    public function phpType(): string
    {
        $type = match ($this->type) {
            'int' => 'int',
            'float' => 'float',
            'bool' => 'bool',
            'date', 'datetime' => '\DateTimeImmutable',
            default => 'string',
        };

        return $this->nullable ? $type . '|null' : $type;
    }

    /**
     * Campo del formulario. Con $variable (edición) toma el valor del modelo.
     */
    public function input(?string $variable): string
    {
        $value = $variable === null ? null : '$' . $variable . '->' . $this->name;
        $required = $this->nullable ? '' : ' required';
        $label = $this->label();

        return match ($this->type) {
            'text' => sprintf('<x-textarea name="%s" label="%s"%s%s />', $this->name, $label, $value === null ? '' : ' :value="' . $value . '"', $required),
            'bool' => sprintf('<x-checkbox name="%s" label="%s"%s />', $this->name, $label, $value === null ? '' : ' :checked="' . $value . '"'),
            default => sprintf(
                '<x-input name="%s" type="%s" label="%s"%s%s%s />',
                $this->name,
                match ($this->type) {
                    'email' => 'email',
                    'int', 'float' => 'number',
                    'date' => 'date',
                    'datetime' => 'datetime-local',
                    default => 'text',
                },
                $label,
                $this->type === 'float' ? ' step="any"' : '',
                $value === null ? '' : ' :value="' . $this->formValue($value) . '"',
                $required
            ),
        };
    }

    /**
     * Expresión para mostrar el valor en una vista.
     */
    public function display(string $variable): string
    {
        $value = '$' . $variable . '->' . $this->name;

        return match ($this->type) {
            'bool' => "{{ {$value} ? 'Sí' : 'No' }}",
            'date' => "{{ {$value}?->format('Y-m-d') }}",
            'datetime' => "{{ {$value}?->format('Y-m-d H:i') }}",
            'text' => "<span class=\"whitespace-pre-line\">{{ {$value} }}</span>",
            default => "{{ {$value} }}",
        };
    }

    /**
     * Valor de ejemplo para los tests generados (como llegaría de un formulario).
     */
    public function sample(bool $updated = false): string
    {
        return match ($this->type) {
            'string' => $updated ? Str::headline($this->name) . ' actualizado' : 'Ejemplo de ' . str_replace('_', ' ', $this->name),
            'text' => $updated ? 'Texto actualizado' : 'Texto de ejemplo',
            'email' => $updated ? 'otro@ejemplo.com' : 'ana@ejemplo.com',
            'int' => $updated ? '7' : '3',
            'float' => $updated ? '2.75' : '1.5',
            'bool' => $updated ? '0' : '1',
            'date' => $updated ? '2026-02-20' : '2026-01-15',
            default => $updated ? '2026-02-20T18:00' : '2026-01-15T10:30',
        };
    }

    /**
     * Indica si el valor se guarda tal cual llega (sirve para assertDatabaseHas en los tests).
     */
    public function isPlainText(): bool
    {
        return in_array($this->type, ['string', 'text', 'email'], true);
    }

    private function formValue(string $value): string
    {
        return match ($this->type) {
            'date' => $value . "?->format('Y-m-d')",
            'datetime' => $value . "?->format('Y-m-d\\\\TH:i')",
            default => $value,
        };
    }
}
