<?php

/**
 * Model.php
 *
 * Active Record ligero: una clase por tabla con CRUD, asignación masiva controlada ($fillable),
 * conversión de tipos ($casts), timestamps y relaciones hasMany/belongsTo. Sin eventos, sin scopes
 * globales y sin magia adicional: lo que no está aquí se escribe con el query builder.
 *
 * @package    BootZen\Database
 * @author     Arturo Lopez <lgzarturo@gmail.com>
 * @copyright  2025 BootZen
 * @license    MIT
 * @since      2.0.0
 */

declare(strict_types=1);

namespace BootZen\Database;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;

/**
 * Modelo base.
 *
 * @example
 *   /**
 *    * @property int $id
 *    * @property string $titulo
 *    * @property bool $hecha
 *    *\/
 *   final class Tarea extends Model
 *   {
 *       protected static string $table = 'tareas';
 *       protected static array $fillable = ['titulo', 'hecha'];
 *       protected static array $casts = ['hecha' => 'bool'];
 *   }
 */
abstract class Model implements JsonSerializable
{
    /**
     * Conversiones soportadas en $casts.
     */
    private const CASTS = ['int', 'float', 'bool', 'string', 'array', 'datetime', 'date'];

    /**
     * Tabla del modelo. Obligatoria.
     */
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    /**
     * Columnas que se pueden asignar con fill(), create() y update(). Las demás se ignoran.
     *
     * @var list<string>
     */
    protected static array $fillable = [];

    /**
     * Columnas que no aparecen en toArray() ni en JSON (contraseñas, tokens).
     *
     * @var list<string>
     */
    protected static array $hidden = [];

    /**
     * Conversiones por columna: int, float, bool, string, array, datetime, date.
     *
     * @var array<string, string>
     */
    protected static array $casts = [];

    /**
     * Mantener created_at y updated_at.
     */
    protected static bool $timestamps = true;

    /**
     * @var (Closure(): Connection)|null
     */
    private static ?Closure $connectionResolver = null;

    /**
     * Valores tal como se guardan en la base de datos.
     *
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * Valores leídos de la base de datos, para detectar cambios.
     *
     * @var array<string, mixed>
     */
    private array $original = [];

    private bool $exists = false;

    /**
     * @param array<string, mixed> $attributes Valores iniciales (solo los de $fillable)
     */
    final public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Define cómo obtener la conexión (lo hace Application).
     *
     * @param (Closure(): Connection)|null $resolver
     */
    public static function setConnectionResolver(?Closure $resolver): void
    {
        self::$connectionResolver = $resolver;
    }

    /**
     * @throws LogicException Si no hay conexión configurada
     */
    public static function connection(): Connection
    {
        if (self::$connectionResolver === null) {
            throw new LogicException('Los modelos no tienen conexión: crea la Application (bootstrap/app.php) antes de usarlos.');
        }

        return (self::$connectionResolver)();
    }

    /**
     * @throws LogicException Si el modelo no define $table
     */
    public static function table(): string
    {
        if (static::$table === '') {
            throw new LogicException('El modelo ' . static::class . " debe definir protected static string \$table = '...';");
        }

        return static::$table;
    }

    public static function primaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * Consulta sobre la tabla del modelo.
     *
     * @return ModelQuery<static>
     */
    public static function query(): ModelQuery
    {
        return new ModelQuery(static::class, static::connection()->table(static::table()));
    }

    /**
     * @return list<static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * @throws ModelNotFoundException 404 si no existe
     */
    public static function findOrFail(int|string $id): static
    {
        return static::query()->findOrFail($id);
    }

    /**
     * Inicia una consulta con una condición. Con dos argumentos el operador es '='.
     *
     * @return ModelQuery<static>
     */
    public static function where(string $column, mixed $operator, mixed $value = null): ModelQuery
    {
        return func_num_args() === 2
            ? static::query()->where($column, $operator)
            : static::query()->where($column, $operator, $value);
    }

    /**
     * Crea y guarda un registro con los valores de $fillable.
     *
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $model = self::newInstance();
        $model->fill($attributes)->save();

        return $model;
    }

    /**
     * Crea una instancia a partir de una fila de la base de datos.
     *
     * @param array<string, mixed> $row
     */
    public static function hydrate(array $row): static
    {
        $model = self::newInstance();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;

        return $model;
    }

    /**
     * Asigna valores de $fillable; el resto se ignora (por ejemplo _token o campos ajenos).
     *
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable, true)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Inserta o actualiza el registro. Solo se envían las columnas que cambiaron.
     */
    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');
        $query = static::connection()->table(static::table());

        if (! $this->exists) {
            if (static::$timestamps) {
                $this->attributes['created_at'] ??= $now;
                $this->attributes['updated_at'] ??= $now;
            }

            $id = $query->insert($this->attributes);

            if (! isset($this->attributes[static::$primaryKey]) && $id !== '' && $id !== '0') {
                $this->attributes[static::$primaryKey] = ctype_digit($id) ? (int) $id : $id;
            }

            $this->exists = true;
            $this->original = $this->attributes;

            return true;
        }

        $dirty = $this->getDirty();
        if ($dirty === []) {
            return true;
        }

        if (static::$timestamps) {
            $this->attributes['updated_at'] = $now;
            $dirty['updated_at'] = $now;
        }

        $query->where(static::$primaryKey, $this->getKey())->update($dirty);
        $this->original = $this->attributes;

        return true;
    }

    /**
     * Asigna valores de $fillable y guarda.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }

    /**
     * Borra el registro. Devuelve false si no estaba guardado.
     */
    public function delete(): bool
    {
        if (! $this->exists) {
            return false;
        }

        static::connection()->table(static::table())->where(static::$primaryKey, $this->getKey())->delete();
        $this->exists = false;

        return true;
    }

    /**
     * Vuelve a leer el registro de la base de datos.
     *
     * @throws ModelNotFoundException Si ya no existe
     */
    public function refresh(): static
    {
        $key = $this->getKey();
        $row = $key === null ? null : static::connection()->table(static::table())->find($key, static::$primaryKey);

        if ($row === null) {
            throw new ModelNotFoundException(static::class, $key);
        }

        $this->attributes = $row;
        $this->original = $row;

        return $this;
    }

    public function getKey(): int|string|null
    {
        $key = $this->attributes[static::$primaryKey] ?? null;

        return is_int($key) || is_string($key) ? $key : null;
    }

    /**
     * Indica si el registro está guardado en la base de datos.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Columnas modificadas desde la última lectura o guardado.
     *
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        return array_filter(
            $this->attributes,
            fn (mixed $value, string $key): bool => ! array_key_exists($key, $this->original) || $this->original[$key] !== $value,
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function isDirty(): bool
    {
        return $this->getDirty() !== [];
    }

    /**
     * Valor de una columna con su conversión de $casts.
     */
    public function getAttribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;
        $cast = $this->castFor($key);

        if ($value === null || $cast === null) {
            return $value;
        }

        return match ($cast) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => is_scalar($value) ? (string) $value : '',
            'array' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => $value instanceof DateTimeInterface ? $value : new DateTimeImmutable((string) $value),
            'date' => $value instanceof DateTimeInterface
                ? $value
                : (DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10)) ?: null),
            default => $value,
        };
    }

    /**
     * Asigna una columna convirtiendo el valor al formato de la base de datos.
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $cast = $this->castFor($key);

        $this->attributes[$key] = match (true) {
            $value === null => null,
            $cast === 'array' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $cast === 'bool' || is_bool($value) => $value ? 1 : 0,
            $cast === 'date' && $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        };
    }

    /**
     * Valores con sus conversiones, sin las columnas de $hidden. Las fechas como 'Y-m-d H:i:s'.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [];

        foreach (array_keys($this->attributes) as $key) {
            if (in_array($key, static::$hidden, true)) {
                continue;
            }

            $value = $this->getAttribute($key);
            $array[$key] = match (true) {
                $value instanceof DateTimeInterface && $this->castFor($key) === 'date' => $value->format('Y-m-d'),
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => $value,
            };
        }

        return $array;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Relación uno a muchos: devuelve una consulta para encadenar o llamar get().
     *
     * @template TRelated of Model
     * @param class-string<TRelated> $related
     * @param string|null $foreignKey Columna en la tabla relacionada (por defecto: modelo_id)
     * @return ModelQuery<TRelated>
     * @example public function tareas(): ModelQuery { return $this->hasMany(Tarea::class); }
     */
    protected function hasMany(string $related, ?string $foreignKey = null): ModelQuery
    {
        return $related::query()->where($foreignKey ?? self::foreignKeyFor(static::class), $this->getKey());
    }

    /**
     * Relación inversa: el modelo dueño, o null si la columna está vacía.
     *
     * @template TRelated of Model
     * @param class-string<TRelated> $related
     * @param string|null $foreignKey Columna en esta tabla (por defecto: relacionado_id)
     * @return TRelated|null
     * @example public function proyecto(): ?Proyecto { return $this->belongsTo(Proyecto::class); }
     */
    protected function belongsTo(string $related, ?string $foreignKey = null): ?Model
    {
        $id = $this->attributes[$foreignKey ?? self::foreignKeyFor($related)] ?? null;

        return is_int($id) || is_string($id) ? $related::find($id) : null;
    }

    /**
     * Crea una instancia vacía del modelo concreto.
     *
     * @throws LogicException Si se llama sobre la clase base Model
     */
    private static function newInstance(): static
    {
        $reflection = new \ReflectionClass(static::class);

        if ($reflection->isAbstract()) {
            throw new LogicException('Usa un modelo concreto (por ejemplo Tarea::create()), no la clase base Model.');
        }

        return $reflection->newInstance();
    }

    /**
     * @throws InvalidArgumentException Si el cast no existe
     */
    private function castFor(string $key): ?string
    {
        $cast = static::$casts[$key] ?? (static::$timestamps && in_array($key, ['created_at', 'updated_at'], true) ? 'datetime' : null);

        if ($cast === null) {
            return null;
        }

        $cast = match ($cast) {
            'integer' => 'int',
            'boolean' => 'bool',
            'json' => 'array',
            default => $cast,
        };

        if (! in_array($cast, self::CASTS, true)) {
            throw new InvalidArgumentException(
                "Cast [{$cast}] desconocido en " . static::class . '. Usa: ' . implode(', ', self::CASTS) . '.'
            );
        }

        return $cast;
    }

    /**
     * Columna de clave foránea por convención: Proyecto → proyecto_id, OrdenCompra → orden_compra_id.
     */
    private static function foreignKeyFor(string $class): string
    {
        $basename = substr((string) strrchr('\\' . $class, '\\'), 1);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $basename)) . '_id';
    }
}
