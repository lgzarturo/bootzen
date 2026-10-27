<?php

declare(strict_types=1);

namespace App\Models;

use BootZen\Database\Model;

/**
 * Tarea de un usuario.
 *
 * @property int $id
 * @property int $user_id
 * @property string $titulo
 * @property string|null $notas
 * @property bool $hecha
 * @property \DateTimeImmutable|null $vence
 * @property \DateTimeImmutable|null $created_at
 * @property \DateTimeImmutable|null $updated_at
 */
final class Tarea extends Model
{
    protected static string $table = 'tareas';

    protected static array $fillable = ['user_id', 'titulo', 'notas', 'hecha', 'vence'];

    protected static array $casts = ['user_id' => 'int', 'hecha' => 'bool', 'vence' => 'date'];

    public function user(): ?User
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Indica si venció sin estar hecha.
     */
    public function vencida(): bool
    {
        return ! $this->hecha && $this->vence !== null && $this->vence < new \DateTimeImmutable('today');
    }
}
