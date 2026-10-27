<?php

declare(strict_types=1);

namespace App\Models;

use BootZen\Database\Model;
use BootZen\Database\ModelQuery;

/**
 * Usuario de la aplicación. La contraseña se guarda con Hash::make() y nunca sale en JSON.
 *
 * @property int $id
 * @property string $nombre
 * @property string $email
 * @property string $password
 * @property \DateTimeImmutable|null $created_at
 * @property \DateTimeImmutable|null $updated_at
 */
final class User extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = ['nombre', 'email', 'password'];

    protected static array $hidden = ['password'];

    /**
     * @return ModelQuery<Tarea>
     */
    public function tareas(): ModelQuery
    {
        return $this->hasMany(Tarea::class);
    }
}
