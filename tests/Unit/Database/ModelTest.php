<?php

declare(strict_types=1);

use BootZen\Database\Model;
use BootZen\Database\ModelNotFoundException;
use BootZen\Database\ModelQuery;

/**
 * @property int $id
 * @property string $nombre
 */
final class ModelTestProyecto extends Model
{
    protected static string $table = 'proyectos';

    protected static array $fillable = ['nombre'];

    protected static bool $timestamps = false;

    /**
     * @return ModelQuery<ModelTestTarea>
     */
    public function tareas(): ModelQuery
    {
        return $this->hasMany(ModelTestTarea::class, 'proyecto_id');
    }
}

/**
 * @property int $id
 * @property int|null $proyecto_id
 * @property string $titulo
 * @property bool $hecha
 * @property array<string, mixed>|null $meta
 * @property DateTimeImmutable|null $vence_en
 */
final class ModelTestTarea extends Model
{
    protected static string $table = 'tareas';

    protected static array $fillable = ['titulo', 'hecha', 'prioridad', 'meta', 'vence_en', 'proyecto_id'];

    protected static array $hidden = ['meta'];

    protected static array $casts = ['hecha' => 'boolean', 'prioridad' => 'integer', 'meta' => 'json', 'vence_en' => 'date'];

    public function proyecto(): ?ModelTestProyecto
    {
        return $this->belongsTo(ModelTestProyecto::class, 'proyecto_id');
    }
}

final class ModelTestSinTabla extends Model
{
}

final class ModelTestCastRaro extends Model
{
    protected static string $table = 'tareas';

    protected static array $casts = ['titulo' => 'decimal'];
}

beforeEach(function (): void {
    $this->db = memoryDb();
    Model::setConnectionResolver(fn () => $this->db);
});

afterEach(function (): void {
    Model::setConnectionResolver(null);
});

it('crea, lee, actualiza y borra registros', function (): void {
    $tarea = ModelTestTarea::create(['titulo' => 'Pan', 'hecha' => false]);

    expect($tarea->id)->toBe(1)
        ->and($tarea->exists())->toBeTrue()
        ->and(ModelTestTarea::find(1)?->titulo)->toBe('Pan');

    $tarea->titulo = 'Pan integral';
    $tarea->save();
    $tarea->update(['hecha' => true]);

    $leida = ModelTestTarea::findOrFail(1);

    expect($leida->titulo)->toBe('Pan integral')
        ->and($leida->hecha)->toBeTrue()
        ->and($leida->delete())->toBeTrue()
        ->and($leida->delete())->toBeFalse()
        ->and(ModelTestTarea::find(1))->toBeNull();
});

it('solo asigna columnas de $fillable', function (): void {
    $tarea = ModelTestTarea::create(['titulo' => 'Pan', 'id' => 99, '_csrf_token' => 'x']);

    expect($tarea->id)->toBe(1)
        ->and(isset($tarea->_csrf_token))->toBeFalse();
});

it('convierte tipos al leer y al guardar', function (): void {
    $tarea = ModelTestTarea::create([
        'titulo' => 'Pan',
        'hecha' => 1,
        'prioridad' => '3',
        'meta' => ['etiquetas' => ['casa'], 'nota' => 'ñ'],
        'vence_en' => new DateTimeImmutable('2026-11-01 15:00'),
    ]);
    $fila = $this->db->selectOne('SELECT hecha, meta, vence_en FROM tareas');
    $leida = ModelTestTarea::findOrFail($tarea->id);

    expect($fila)->toBe(['hecha' => 1, 'meta' => '{"etiquetas":["casa"],"nota":"ñ"}', 'vence_en' => '2026-11-01'])
        ->and($leida->hecha)->toBeTrue()
        ->and($leida->prioridad)->toBe(3)
        ->and($leida->meta)->toBe(['etiquetas' => ['casa'], 'nota' => 'ñ'])
        ->and($leida->vence_en?->format('Y-m-d H:i'))->toBe('2026-11-01 00:00')
        ->and($leida->created_at)->toBeInstanceOf(DateTimeImmutable::class);
});

it('mantiene timestamps y solo actualiza lo que cambió', function (): void {
    $tarea = ModelTestTarea::create(['titulo' => 'Pan']);
    $creada = $this->db->selectOne('SELECT created_at, updated_at FROM tareas');

    expect($creada['created_at'] ?? null)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/')
        ->and($tarea->isDirty())->toBeFalse();

    $tarea->titulo = 'Pan';
    expect($tarea->isDirty())->toBeFalse();

    $tarea->hecha = true;
    expect($tarea->getDirty())->toBe(['hecha' => 1])
        ->and($tarea->save())->toBeTrue()
        ->and($tarea->isDirty())->toBeFalse();
});

it('serializa a array y JSON sin las columnas ocultas', function (): void {
    ModelTestTarea::create(['titulo' => 'Pan', 'meta' => ['x' => 1], 'vence_en' => '2026-11-01']);
    $array = ModelTestTarea::findOrFail(1)->toArray();

    expect($array)->not->toHaveKey('meta')
        ->and($array['vence_en'])->toBe('2026-11-01')
        ->and($array['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/')
        ->and(json_decode((string) json_encode(ModelTestTarea::findOrFail(1)), true))->toBe($array);
});

it('consulta con where, orden, conteo y paginación', function (): void {
    foreach (['Pan', 'Leche', 'Café'] as $i => $titulo) {
        ModelTestTarea::create(['titulo' => $titulo, 'hecha' => $i === 1]);
    }

    $pendientes = ModelTestTarea::where('hecha', false)->orderBy('titulo')->get();
    $pagina = ModelTestTarea::query()->orderBy('id')->paginate(2, 2);

    expect(array_map(fn (ModelTestTarea $t): string => $t->titulo, $pendientes))->toBe(['Café', 'Pan'])
        ->and(ModelTestTarea::where('titulo', 'like', 'L%')->first()?->titulo)->toBe('Leche')
        ->and(ModelTestTarea::query()->whereIn('id', [1, 2])->count())->toBe(2)
        ->and(ModelTestTarea::all())->toHaveCount(3)
        ->and($pagina->items()[0])->toBeInstanceOf(ModelTestTarea::class)
        ->and($pagina->items()[0]->titulo)->toBe('Café')
        ->and($pagina->total())->toBe(3)
        ->and(ModelTestTarea::query()->pluck('titulo'))->toBe(['Pan', 'Leche', 'Café'])
        ->and(ModelTestTarea::where('hecha', true)->update(['hecha' => false]))->toBe(1)
        ->and(ModelTestTarea::where('titulo', 'Café')->delete())->toBe(1);
});

it('relaciona modelos con hasMany y belongsTo', function (): void {
    $proyecto = ModelTestProyecto::create(['nombre' => 'Casa']);
    ModelTestTarea::create(['titulo' => 'Pan', 'proyecto_id' => $proyecto->id]);
    ModelTestTarea::create(['titulo' => 'Leche', 'proyecto_id' => $proyecto->id]);
    $suelta = ModelTestTarea::create(['titulo' => 'Sola']);

    expect($proyecto->tareas()->count())->toBe(2)
        ->and($proyecto->tareas()->orderBy('titulo')->first()?->titulo)->toBe('Leche')
        ->and(ModelTestTarea::findOrFail(1)->proyecto()?->nombre)->toBe('Casa')
        ->and($suelta->proyecto())->toBeNull();
});

it('lanza 404 cuando no encuentra el registro', function (): void {
    try {
        ModelTestTarea::findOrFail(42);
        $this->fail('Se esperaba ModelNotFoundException');
    } catch (ModelNotFoundException $e) {
        expect($e->getStatusCode())->toBe(404)
            ->and($e->getModel())->toBe(ModelTestTarea::class)
            ->and($e->getId())->toBe(42);
    }

    expect(fn () => ModelTestTarea::where('titulo', 'Nada')->firstOrFail())->toThrow(ModelNotFoundException::class);
});

it('recarga el registro desde la base de datos', function (): void {
    $tarea = ModelTestTarea::create(['titulo' => 'Pan']);
    $this->db->statement('UPDATE tareas SET titulo = ? WHERE id = ?', ['Cambiado', 1]);

    expect($tarea->refresh()->titulo)->toBe('Cambiado');

    $this->db->statement('DELETE FROM tareas');

    expect(fn () => $tarea->refresh())->toThrow(ModelNotFoundException::class);
});

it('explica los errores de configuración del modelo', function (): void {
    expect(fn () => ModelTestSinTabla::all())->toThrow(LogicException::class, 'debe definir protected static string $table')
        ->and(fn () => (new ModelTestCastRaro())->setAttribute('titulo', '1'))->toThrow(InvalidArgumentException::class, 'Cast [decimal] desconocido');

    Model::setConnectionResolver(null);

    expect(fn () => ModelTestTarea::all())->toThrow(LogicException::class, 'no tienen conexión');
});

it('permite quitar atributos y consultar el builder base', function (): void {
    $tarea = new ModelTestTarea(['titulo' => 'Pan']);
    unset($tarea->titulo);

    expect(isset($tarea->titulo))->toBeFalse()
        ->and($tarea->getKey())->toBeNull()
        ->and(ModelTestTarea::query()->limit(1)->offset(1)->toBase()->toSql())->toContain('LIMIT 1 OFFSET 1');
});

it('deriva la clave foránea del nombre de la clase', function (): void {
    $foreignKeyFor = Closure::bind(fn (string $class): string => self::foreignKeyFor($class), null, Model::class);

    expect($foreignKeyFor('App\\Models\\Proyecto'))->toBe('proyecto_id')
        ->and($foreignKeyFor('App\\Models\\OrdenCompra'))->toBe('orden_compra_id')
        ->and($foreignKeyFor('Usuario'))->toBe('usuario_id');
});
