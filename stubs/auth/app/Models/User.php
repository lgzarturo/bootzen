<?php

declare(strict_types=1);

namespace App\Models;

use BootZen\Auth\Authorizable;
use BootZen\Database\Model;

/**
 * Usuario de la aplicación. La contraseña se guarda con Hash::make() y nunca sale en JSON.
 *
 * @property int $id
 * @property string $nombre
 * @property string $email
 * @property string $password
 * @property string $role
 * @property \DateTimeImmutable|null $email_verified_at
 * @property \DateTimeImmutable|null $created_at
 * @property \DateTimeImmutable|null $updated_at
 *
 * @method static \Database\Factories\UserFactory factory()
 */
final class User extends Model
{
    use Authorizable;

    protected static string $table = 'users';

    protected static array $fillable = ['nombre', 'email', 'password'];

    protected static array $hidden = ['password'];

    protected static array $casts = ['email_verified_at' => 'datetime'];
}
