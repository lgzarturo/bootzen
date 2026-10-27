<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->db = memoryDb();
    foreach ([['Pan', 0, 2], ['Leche', 1, 1], ['Café', 0, null], ['Té', 1, 3]] as [$titulo, $hecha, $prioridad]) {
        $this->db->table('tareas')->insert(['titulo' => $titulo, 'hecha' => $hecha, 'prioridad' => $prioridad]);
    }
});

it('arma consultas con where, orden y límite', function (): void {
    $query = $this->db->table('tareas')
        ->select('id', 'titulo as nombre')
        ->where('hecha', false)
        ->where('prioridad', '>=', 1)
        ->orderBy('prioridad', 'desc')
        ->limit(5)
        ->offset(0);

    expect($query->toSql())->toBe(
        'SELECT "id", "titulo" AS "nombre" FROM "tareas" WHERE "hecha" = ? AND "prioridad" >= ? ORDER BY "prioridad" DESC LIMIT 5 OFFSET 0'
    )->and($query->getBindings())->toBe([false, 1])
        ->and($query->get())->toBe([['id' => 1, 'nombre' => 'Pan']]);
});

it('combina orWhere, whereIn, whereNull y whereRaw', function (): void {
    $titulos = fn ($query): array => $query->orderBy('id')->pluck('titulo');

    expect($titulos($this->db->table('tareas')->where('titulo', 'Pan')->orWhere('titulo', 'Té')))->toBe(['Pan', 'Té'])
        ->and($titulos($this->db->table('tareas')->whereIn('id', [2, 3])))->toBe(['Leche', 'Café'])
        ->and($titulos($this->db->table('tareas')->whereNotIn('id', [1, 2, 3])))->toBe(['Té'])
        ->and($titulos($this->db->table('tareas')->whereIn('id', [])))->toBe([])
        ->and($titulos($this->db->table('tareas')->whereNotIn('id', [])))->toHaveCount(4)
        ->and($titulos($this->db->table('tareas')->whereNull('prioridad')))->toBe(['Café'])
        ->and($titulos($this->db->table('tareas')->whereNotNull('prioridad')->where('prioridad', '!=', null)))->toHaveCount(3)
        ->and($titulos($this->db->table('tareas')->where('prioridad', null)))->toBe(['Café'])
        ->and($titulos($this->db->table('tareas')->whereRaw('lower(titulo) like ?', ['%a%'])))->toBe(['Pan', 'Café'])
        ->and($titulos($this->db->table('tareas')->where('titulo', 'like', 'C%')))->toBe(['Café']);
});

it('obtiene filas, valores y agregados', function (): void {
    $tareas = $this->db->table('tareas');

    expect($tareas->find(2)['titulo'] ?? null)->toBe('Leche')
        ->and($tareas->find(99))->toBeNull()
        ->and($this->db->table('tareas')->where('hecha', true)->value('titulo'))->toBe('Leche')
        ->and($this->db->table('tareas')->where('hecha', true)->count())->toBe(2)
        ->and($this->db->table('tareas')->where('titulo', 'Nada')->exists())->toBeFalse()
        ->and($this->db->table('tareas')->latest('id')->first()['titulo'] ?? null)->toBe('Té')
        ->and($this->db->table('tareas')->selectRaw('max(prioridad) as maxima')->first())->toBe(['maxima' => 3]);
});

it('hace joins entre tablas', function (): void {
    $proyecto = $this->db->table('proyectos')->insert(['nombre' => 'Casa']);
    $this->db->table('tareas')->where('id', 1)->update(['proyecto_id' => (int) $proyecto]);

    $rows = $this->db->table('tareas')
        ->select('tareas.titulo', 'proyectos.nombre as proyecto')
        ->join('proyectos', 'proyectos.id', '=', 'tareas.proyecto_id')
        ->get();

    expect($rows)->toBe([['titulo' => 'Pan', 'proyecto' => 'Casa']])
        ->and($this->db->table('tareas')->leftJoin('proyectos', 'proyectos.id', '=', 'tareas.proyecto_id')->count())->toBe(4);
});

it('actualiza y borra solo con where', function (): void {
    expect($this->db->table('tareas')->where('hecha', true)->update(['hecha' => false]))->toBe(2)
        ->and($this->db->table('tareas')->where('id', 1)->update([]))->toBe(0)
        ->and($this->db->table('tareas')->where('titulo', 'Pan')->delete())->toBe(1)
        ->and($this->db->table('tareas')->whereRaw('1 = 1')->delete())->toBe(3)
        ->and(fn () => $this->db->table('tareas')->update(['hecha' => true]))->toThrow(LogicException::class, "whereRaw('1 = 1')")
        ->and(fn () => $this->db->table('tareas')->delete())->toThrow(LogicException::class, 'todas las filas');
});

it('pagina resultados', function (): void {
    $page = $this->db->table('tareas')->orderBy('id')->paginate(3, 2);

    expect($page->items())->toHaveCount(1)
        ->and($page->items()[0]['titulo'])->toBe('Té')
        ->and([$page->total(), $page->perPage(), $page->currentPage(), $page->lastPage()])->toBe([4, 3, 2, 2]);
});

it('rechaza identificadores, operadores y direcciones inválidos', function (Closure $query, string $message): void {
    expect(fn () => $query($this->db->table('tareas')))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'columna con SQL' => [fn ($q) => $q->select('id; DROP TABLE tareas'), 'no es un nombre de columna'],
    'operador' => [fn ($q) => $q->where('id', 'regexp', 'x'), 'Operador [regexp] no permitido'],
    'operador no string' => [fn ($q) => $q->where('id', 5, 6), 'debe ser un string'],
    'dirección' => [fn ($q) => $q->orderBy('id', 'sideways'), 'Dirección de orden'],
    'join' => [fn ($q) => $q->join('proyectos', 'a', '=', 'b', 'cross'), 'Tipo de join'],
    'insert vacío' => [fn ($q) => $q->insert([]), 'al menos una columna'],
]);
