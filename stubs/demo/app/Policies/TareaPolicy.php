<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tarea;
use App\Models\User;

/**
 * Cada quien ve y cambia solo sus tareas. El admin (rol con '*') pasa sin preguntarle a la política.
 * Se usa con authorize('update', $tarea) en TareaController y can('update', $tarea) en las vistas.
 */
final class TareaPolicy
{
    public function view(User $user, Tarea $tarea): bool
    {
        return $this->owns($user, $tarea);
    }

    public function update(User $user, Tarea $tarea): bool
    {
        return $this->owns($user, $tarea);
    }

    public function delete(User $user, Tarea $tarea): bool
    {
        return $this->owns($user, $tarea);
    }

    private function owns(User $user, Tarea $tarea): bool
    {
        return $tarea->user_id === (int) $user->getKey();
    }
}
