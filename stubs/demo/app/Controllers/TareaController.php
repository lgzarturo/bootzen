<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Tarea;
use App\Models\User;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Database\ModelQuery;

/**
 * Tareas del usuario con sesión. Todo pasa por $this->tareas(): nadie ve ni toca tareas ajenas.
 *
 * GET /tareas responde HTML o, si el cliente pide JSON, la lista paginada:
 * {"data": [...], "total": 3, "per_page": 15, "current_page": 1, "last_page": 1}.
 */
final class TareaController
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'estado' => 'nullable|in:pendientes,hechas',
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        $query = $this->tareas()->orderBy('hecha')->orderBy('id', 'desc');

        if ($filtros['estado'] ?? null) {
            $query->where('hecha', $filtros['estado'] === 'hechas');
        }

        $tareas = $query->paginate((int) ($filtros['per_page'] ?? 15), $request->integer('page', 1));

        if ($request->wantsJson()) {
            return Response::json($tareas);
        }

        return view('tareas.index', ['tareas' => $tareas->appends(['estado' => $filtros['estado'] ?? null]), 'estado' => $filtros['estado'] ?? null]);
    }

    public function create(): Response
    {
        return view('tareas.create');
    }

    public function store(Request $request): Response
    {
        Tarea::create(['user_id' => $this->usuario()->id] + $request->validate($this->rules()));

        return redirect(route('tareas.index'))->with('status', 'Tarea creada.');
    }

    public function edit(int $id): Response
    {
        return view('tareas.edit', ['tarea' => $this->find($id)]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->find($id)->update($request->validate($this->rules()));

        return redirect(route('tareas.index'))->with('status', 'Tarea actualizada.');
    }

    /**
     * Marca o desmarca la tarea como hecha (botón del listado).
     */
    public function completar(int $id): Response
    {
        $tarea = $this->find($id);
        $tarea->update(['hecha' => ! $tarea->hecha]);

        return back()->with('status', $tarea->hecha ? 'Tarea hecha.' : 'Tarea pendiente otra vez.');
    }

    public function destroy(int $id): Response
    {
        $this->find($id)->delete();

        return redirect(route('tareas.index'))->with('status', 'Tarea borrada.');
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'titulo' => 'required|string|max:200',
            'notas' => 'nullable|string|max:2000',
            'hecha' => 'boolean',
            'vence' => 'nullable|date',
        ];
    }

    /**
     * Busca la tarea solo entre las del usuario: si es de otro, 404 (no revela que existe).
     */
    private function find(int $id): Tarea
    {
        return $this->tareas()->findOrFail($id);
    }

    /**
     * @return ModelQuery<Tarea>
     */
    private function tareas(): ModelQuery
    {
        return $this->usuario()->tareas();
    }

    private function usuario(): User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : abort(401);
    }
}
