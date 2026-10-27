<?php

/**
 * Validator.php
 *
 * Validación de datos con reglas tipo 'required|email|max:120' y mensajes en español.
 *
 * Reglas: required, nullable, string, integer, numeric, boolean, array, email, url, min, max, between,
 * in, confirmed, date, regex, unique, exists, file, image y mimes. Admite comodines ('items.*.precio')
 * y closures fn (mixed $valor, string $campo): ?string que devuelven el mensaje de error o null.
 *
 * @package    BootZen\Validation
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Validation;

use BootZen\Database\Connection;
use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * Valida un array de datos contra un conjunto de reglas.
 *
 * Un campo sin 'required' que llega vacío (null, '' o sin archivo) se acepta sin revisar sus otras reglas
 * y en validated() queda como null. Cada campo muestra solo el primer error que encuentra.
 *
 * @example $datos = Validator::make($request->all(), ['email' => 'required|email'])->validate();
 */
final class Validator
{
    /**
     * Mensajes por defecto. Las reglas de tamaño tienen una variante por tipo.
     */
    private const MESSAGES = [
        'required' => 'El campo :attribute es obligatorio.',
        'string' => 'El campo :attribute debe ser texto.',
        'integer' => 'El campo :attribute debe ser un número entero.',
        'numeric' => 'El campo :attribute debe ser un número.',
        'boolean' => 'El campo :attribute debe ser verdadero o falso.',
        'array' => 'El campo :attribute debe ser una lista.',
        'email' => 'El campo :attribute debe ser un correo electrónico válido.',
        'url' => 'El campo :attribute debe ser una URL válida.',
        'min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'min.numeric' => 'El campo :attribute debe ser al menos :min.',
        'min.array' => 'El campo :attribute debe tener al menos :min elementos.',
        'min.file' => 'El archivo :attribute debe pesar al menos :min KB.',
        'max.string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'max.numeric' => 'El campo :attribute no puede ser mayor que :max.',
        'max.array' => 'El campo :attribute no puede tener más de :max elementos.',
        'max.file' => 'El archivo :attribute no puede pesar más de :max KB.',
        'between.string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
        'between.numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'between.array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'between.file' => 'El archivo :attribute debe pesar entre :min y :max KB.',
        'in' => 'El valor de :attribute no es válido.',
        'confirmed' => 'La confirmación de :attribute no coincide.',
        'date' => 'El campo :attribute debe ser una fecha válida.',
        'regex' => 'El formato de :attribute no es válido.',
        'unique' => 'Ya existe un registro con ese :attribute.',
        'exists' => 'El :attribute seleccionado no existe.',
        'file' => 'El campo :attribute debe ser un archivo.',
        'image' => 'El archivo :attribute debe ser una imagen (jpg, png, gif o webp).',
        'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    ];

    /**
     * Extensión => tipos MIME aceptados (detectados por contenido con finfo, no por el nombre).
     */
    private const MIME_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'json' => ['application/json', 'text/plain'],
        'zip' => ['application/zip'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    /**
     * Resuelve la conexión para unique y exists. La configura Application.
     *
     * @var (Closure(): Connection)|null
     */
    private static ?Closure $connectionResolver = null;

    /**
     * Reglas normalizadas: patrón de campo => lista de reglas.
     *
     * @var array<string, list<string|Closure>>
     */
    private array $rules = [];

    /**
     * @var array<string, list<string>>|null
     */
    private ?array $errors = null;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string|Closure>> $rules
     * @param array<string, string> $messages 'campo.regla' o 'regla' => mensaje
     * @param array<string, string> $attributes Campo => nombre legible
     */
    public function __construct(
        private readonly array $data,
        array $rules,
        private readonly array $messages = [],
        private readonly array $attributes = []
    ) {
        foreach ($rules as $field => $fieldRules) {
            $this->rules[$field] = is_string($fieldRules)
                ? array_values(array_filter(explode('|', $fieldRules), static fn (string $rule): bool => $rule !== ''))
                : $fieldRules;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string|Closure>> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): self
    {
        return new self($data, $rules, $messages, $attributes);
    }

    /**
     * @param (Closure(): Connection)|null $resolver
     */
    public static function setConnectionResolver(?Closure $resolver): void
    {
        self::$connectionResolver = $resolver;
    }

    public function passes(): bool
    {
        return $this->errors() === [];
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * Errores por campo. Los campos con comodín se reportan con su índice real: 'items.0.precio'.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        if ($this->errors !== null) {
            return $this->errors;
        }

        $this->errors = [];

        foreach ($this->rules as $pattern => $rules) {
            foreach ($this->expand($pattern) as $field) {
                $message = $this->validateField($pattern, $field, $rules);

                if ($message !== null) {
                    $this->errors[$field][] = $message;
                }
            }
        }

        return $this->errors;
    }

    /**
     * Devuelve los datos validados o lanza ValidationException.
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors());
        }

        return $this->validated();
    }

    /**
     * Solo los campos que tienen reglas. Los vacíos opcionales quedan como null.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $result = [];

        foreach ($this->rules as $pattern => $rules) {
            foreach ($this->expand($pattern) as $field) {
                [$found, $value] = $this->lookup($field);

                if ($found) {
                    self::assign($result, $field, self::isEmpty($value) && $value !== [] ? null : $value);
                }
            }
        }

        return $result;
    }

    /**
     * Valida un campo concreto y devuelve el primer mensaje de error, o null si pasa.
     *
     * @param list<string|Closure> $rules
     */
    private function validateField(string $pattern, string $field, array $rules): ?string
    {
        [, $value] = $this->lookup($field);

        if (self::isEmpty($value)) {
            return in_array('required', $rules, true) ? $this->message($pattern, $field, 'required', [], $rules) : null;
        }

        foreach ($rules as $rule) {
            if ($rule instanceof Closure) {
                $result = $rule($value, $field);

                if (is_string($result)) {
                    return $result;
                }

                continue;
            }

            [$name, $parameters] = self::parse($rule);

            if (! $this->check($name, $parameters, $field, $value, $rules)) {
                return $this->message($pattern, $field, $name, $parameters, $rules);
            }
        }

        return null;
    }

    /**
     * @param list<string> $parameters
     * @param list<string|Closure> $rules
     * @throws InvalidArgumentException Si la regla no existe
     */
    private function check(string $rule, array $parameters, string $field, mixed $value, array $rules): bool
    {
        return match ($rule) {
            'required', 'nullable' => true,
            'string' => is_string($value),
            'integer' => is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false),
            'numeric' => is_numeric($value),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], true),
            'array' => is_array($value),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false
                && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true),
            'min' => $this->size($value, $rules) >= (float) $this->parameter($parameters, 0, $rule),
            'max' => $this->size($value, $rules) <= (float) $this->parameter($parameters, 0, $rule),
            'between' => $this->size($value, $rules) >= (float) $this->parameter($parameters, 0, $rule)
                && $this->size($value, $rules) <= (float) $this->parameter($parameters, 1, $rule),
            'in' => is_scalar($value) && in_array((string) $value, $parameters, true),
            'confirmed' => $this->lookup($field . '_confirmation')[1] === $value,
            'date' => is_string($value) && strtotime($value) !== false,
            'regex' => is_scalar($value) && preg_match($this->parameter($parameters, 0, $rule), (string) $value) === 1,
            'unique' => ! $this->recordExists($parameters, $field, $value, true),
            'exists' => $this->recordExists($parameters, $field, $value, false),
            'file' => self::isUploadedFile($value),
            'image' => self::isUploadedFile($value)
                && in_array(self::detectMime($value), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true),
            'mimes' => self::isUploadedFile($value) && self::matchesMimes($value, $parameters),
            default => throw new InvalidArgumentException("Regla de validación [{$rule}] desconocida en el campo {$field}."),
        };
    }

    /**
     * Tamaño para min, max y between: valor numérico, elementos de un array, KB de un archivo o caracteres.
     *
     * @param list<string|Closure> $rules
     */
    private function size(mixed $value, array $rules): float
    {
        return match ($this->sizeType($value, $rules)) {
            'numeric' => is_numeric($value) ? (float) $value : 0.0,
            'array' => is_array($value) ? (float) count($value) : 0.0,
            'file' => is_array($value) && is_numeric($value['size'] ?? null) ? (float) $value['size'] / 1024 : 0.0,
            default => is_scalar($value) ? (float) mb_strlen((string) $value) : 0.0,
        };
    }

    /**
     * @param list<string|Closure> $rules
     */
    private function sizeType(mixed $value, array $rules): string
    {
        if (self::isUploadedFile($value)) {
            return 'file';
        }

        if (is_array($value)) {
            return 'array';
        }

        return in_array('numeric', $rules, true) || in_array('integer', $rules, true) ? 'numeric' : 'string';
    }

    /**
     * unique:tabla,columna,idIgnorado,columnaId y exists:tabla,columna. La columna por defecto es el campo.
     *
     * @param list<string> $parameters
     */
    private function recordExists(array $parameters, string $field, mixed $value, bool $unique): bool
    {
        if (self::$connectionResolver === null) {
            throw new LogicException('Las reglas unique y exists necesitan una conexión: crea la Application primero.');
        }

        $segments = explode('.', $field);
        $query = (self::$connectionResolver)()
            ->table($this->parameter($parameters, 0, $unique ? 'unique' : 'exists'))
            ->where($parameters[1] ?? end($segments), is_scalar($value) ? $value : null);

        if ($unique && isset($parameters[2]) && $parameters[2] !== '') {
            $query->where($parameters[3] ?? 'id', '!=', $parameters[2]);
        }

        return $query->exists();
    }

    /**
     * Arma el mensaje: primero 'campo.regla' y 'regla' de los mensajes propios, luego los de por defecto.
     *
     * @param list<string> $parameters
     * @param list<string|Closure> $rules
     */
    private function message(string $pattern, string $field, string $rule, array $parameters, array $rules): string
    {
        [, $value] = $this->lookup($field);
        $sized = in_array($rule, ['min', 'max', 'between'], true) ? $rule . '.' . $this->sizeType($value, $rules) : $rule;

        $template = $this->messages[$field . '.' . $rule]
            ?? $this->messages[$pattern . '.' . $rule]
            ?? $this->messages[$rule]
            ?? self::MESSAGES[$sized]
            ?? "El campo :attribute no es válido ({$rule}).";

        return strtr($template, [
            ':attribute' => $this->attributeName($pattern, $field),
            ':min' => $parameters[0] ?? '',
            ':max' => $rule === 'between' ? ($parameters[1] ?? '') : ($parameters[0] ?? ''),
            ':values' => implode(', ', $parameters),
        ]);
    }

    private function attributeName(string $pattern, string $field): string
    {
        if (isset($this->attributes[$field]) || isset($this->attributes[$pattern])) {
            return $this->attributes[$field] ?? $this->attributes[$pattern];
        }

        $segments = array_filter(explode('.', $field), static fn (string $segment): bool => ! ctype_digit($segment));
        $last = end($segments);

        return str_replace('_', ' ', $last === false ? $field : $last);
    }

    /**
     * Convierte un patrón con comodines en los campos reales: 'items.*.precio' => items.0.precio, items.1.precio.
     *
     * @return list<string>
     */
    private function expand(string $pattern): array
    {
        if (! str_contains($pattern, '*')) {
            return [$pattern];
        }

        $fields = [''];

        foreach (explode('.', $pattern) as $segment) {
            $next = [];

            foreach ($fields as $prefix) {
                if ($segment !== '*') {
                    $next[] = $prefix === '' ? $segment : $prefix . '.' . $segment;

                    continue;
                }

                [$found, $value] = $this->lookup($prefix);
                if ($found && is_array($value)) {
                    foreach (array_keys($value) as $key) {
                        $next[] = $prefix . '.' . $key;
                    }
                }
            }

            $fields = $next;
        }

        return $fields;
    }

    /**
     * Busca un campo con notación de puntos.
     *
     * @return array{0: bool, 1: mixed} [encontrado, valor]
     */
    private function lookup(string $field): array
    {
        $value = $this->data;

        foreach (explode('.', $field) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }

    /**
     * @param array<string, mixed> $target
     */
    private static function assign(array &$target, string $field, mixed $value): void
    {
        $segments = explode('.', $field);
        $last = array_pop($segments);
        $current = &$target;

        foreach ($segments as $segment) {
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[$last] = $value;
    }

    /**
     * @param list<string> $parameters
     * @throws InvalidArgumentException Si falta el parámetro
     */
    private function parameter(array $parameters, int $index, string $rule): string
    {
        return $parameters[$index] ?? throw new InvalidArgumentException("La regla {$rule} necesita parámetros, por ejemplo {$rule}:3.");
    }

    /**
     * 'max:120' => ['max', ['120']]. En regex el parámetro va completo (puede tener comas).
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function parse(string $rule): array
    {
        if (! str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$name, $parameters] = explode(':', $rule, 2);

        return [$name, $name === 'regex' ? [$parameters] : explode(',', $parameters)];
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || $value === []
            || (is_array($value) && ($value['error'] ?? null) === UPLOAD_ERR_NO_FILE && array_key_exists('tmp_name', $value));
    }

    /**
     * Archivo subido sin errores (estructura de $_FILES).
     */
    private static function isUploadedFile(mixed $value): bool
    {
        return is_array($value)
            && ($value['error'] ?? null) === UPLOAD_ERR_OK
            && is_string($value['tmp_name'] ?? null)
            && is_file($value['tmp_name']);
    }

    /**
     * @param array<mixed> $file
     */
    private static function detectMime(array $file): string
    {
        $path = $file['tmp_name'] ?? '';
        $mime = is_string($path) && is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;

        return is_string($mime) ? $mime : '';
    }

    /**
     * @param array<mixed> $file
     * @param list<string> $extensions
     */
    private static function matchesMimes(array $file, array $extensions): bool
    {
        $mime = self::detectMime($file);
        $name = is_string($file['name'] ?? null) ? $file['name'] : '';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        foreach ($extensions as $allowed) {
            $allowed = strtolower(trim($allowed));
            $known = self::MIME_TYPES[$allowed] ?? null;

            // Tipos conocidos se verifican por contenido; el resto, por la extensión del nombre
            if ($known !== null ? in_array($mime, $known, true) : $extension === $allowed) {
                return true;
            }
        }

        return false;
    }
}
