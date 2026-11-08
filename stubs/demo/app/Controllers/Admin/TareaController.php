<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Tarea;
use App\Models\User;
use BootZen\Admin\Listing;
use BootZen\Core\Request;
use BootZen\Core\Response;

/**
 * Tareas de todos los usuarios (generado con make:admin y ajustado: columna del dueño y las tareas
 * nuevas quedan a nombre del admin). Rutas en el grupo /admin de routes/web.php (auth, verified y
 * role:admin). Búsqueda, orden y filtros los decide listado(); cámbialos ahí.
 */
final class TareaController
{
    public function index(Request $request): Response
    {
        $listado = $this->listado($request);
        $tareas = $listado->paginate(20);

        // Los dueños de la página en una sola consulta (no una por fila)
        $ids = array_values(array_unique(array_map(static fn (Tarea $tarea): int => $tarea->user_id, $tareas->items())));
        $duenos = [];
        foreach ($ids === [] ? [] : User::query()->whereIn('id', $ids)->get() as $user) {
            $duenos[$user->id] = $user->nombre;
        }

        return view('admin.tareas.index', ['tareas' => $tareas, 'listado' => $listado, 'duenos' => $duenos]);
    }

    /**
     * CSV con todo lo que coincide con la búsqueda y los filtros (no solo la página).
     */
    public function export(Request $request): Response
    {
        return $this->listado($request)->csv('tareas-' . date('Y-m-d') . '.csv', [
            'id' => 'ID',
            'user_id' => __('Usuario (id)'),
            'titulo' => __('Título'),
            'notas' => __('Notas'),
            'hecha' => __('Hecha'),
            'vence' => __('Vence'),
        ]);
    }

    public function create(): Response
    {
        return view('admin.tareas.create');
    }

    public function store(Request $request): Response
    {
        Tarea::create(['user_id' => auth()->id()] + $request->validate($this->rules()));

        return redirect(route('admin.tareas.index'))->with('status', __('Registro creado.'));
    }

    public function edit(int $id): Response
    {
        return view('admin.tareas.edit', ['tarea' => Tarea::findOrFail($id)]);
    }

    public function update(Request $request, int $id): Response
    {
        Tarea::findOrFail($id)->update($request->validate($this->rules()));

        return redirect(route('admin.tareas.index'))->with('status', __('Registro actualizado.'));
    }

    public function destroy(int $id): Response
    {
        Tarea::findOrFail($id)->delete();

        return redirect(route('admin.tareas.index'))->with('status', __('Registro borrado.'));
    }

    /**
     * @return Listing<Tarea>
     */
    private function listado(Request $request): Listing
    {
        return Listing::from(Tarea::query(), $request)
            ->search(['titulo', 'notas'])
            ->filters(['hecha' => 'bool', 'vence' => 'date'])
            ->sortable(['id', 'titulo', 'hecha', 'vence', 'created_at']);
    }

    /**
     * Reglas de validación para crear y actualizar.
     *
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'notas' => 'nullable|string',
            'hecha' => 'boolean',
            'vence' => 'nullable|date',
        ];
    }
}
