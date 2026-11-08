<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\User;
use BootZen\Admin\Listing;
use BootZen\Core\Request;
use BootZen\Core\Response;
use BootZen\Validation\ValidationException;
use DateTimeImmutable;

/**
 * Usuarios en el panel: buscar, filtrar por rol, cambiar el rol y marcar el correo como verificado.
 * No hay alta ni borrado desde aquí (las cuentas se crean con el registro).
 */
final class UserController
{
    public function index(Request $request): Response
    {
        $listado = $this->listado($request);

        return view('admin.usuarios.index', [
            'usuarios' => $listado->paginate(20),
            'listado' => $listado,
            'roles' => gate()->roles(),
            'rol' => $this->rol($request),
        ]);
    }

    public function export(Request $request): Response
    {
        return $this->listado($request)->csv('usuarios-' . date('Y-m-d') . '.csv', [
            'id' => 'ID',
            'nombre' => __('Nombre'),
            'email' => __('Correo'),
            'role' => __('Rol'),
            'email_verified_at' => __('Correo verificado'),
            'created_at' => __('Alta'),
        ]);
    }

    public function edit(int $id): Response
    {
        return view('admin.usuarios.edit', ['usuario' => User::findOrFail($id), 'roles' => gate()->roles()]);
    }

    public function update(Request $request, int $id): Response
    {
        $usuario = User::findOrFail($id);
        $datos = $request->validate([
            'role' => 'required|in:' . implode(',', gate()->roles()),
            'verificado' => 'boolean',
        ]);

        // Un admin no puede quitarse el rol a sí mismo: se quedaría fuera del panel
        if ($usuario->id === auth()->id() && $datos['role'] !== $usuario->role) {
            throw ValidationException::withMessages(['role' => __('No puedes cambiar tu propio rol.')]);
        }

        // role no está en $fillable: se asigna a propósito, solo aquí
        $usuario->role = (string) $datos['role'];
        $usuario->email_verified_at = ($datos['verificado'] ?? false) ? ($usuario->email_verified_at ?? new DateTimeImmutable()) : null;
        $usuario->save();

        return redirect(route('admin.usuarios.index'))->with('status', __('Usuario actualizado.'));
    }

    /**
     * @return Listing<User>
     */
    private function listado(Request $request): Listing
    {
        $query = User::query();
        $rol = $this->rol($request);

        if ($rol !== '') {
            $query->where('role', $rol);
        }

        return Listing::from($query, $request)
            ->search(['nombre', 'email'])
            ->filters(['email_verified_at' => 'date'])
            ->sortable(['id', 'nombre', 'email', 'role', 'created_at']);
    }

    /**
     * Rol elegido en el filtro, solo si existe en config/auth.php.
     */
    private function rol(Request $request): string
    {
        $rol = $request->getQuery('rol', '');

        return is_string($rol) && in_array($rol, gate()->roles(), true) ? $rol : '';
    }
}
