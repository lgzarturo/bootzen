<?php

/**
 * Modelos y política de prueba para GateTest (las políticas viven en App\Policies).
 */

declare(strict_types=1);

namespace {
    use BootZen\Auth\Authorizable;
    use BootZen\Database\Model;

    /**
     * @property int $id
     * @property string $email
     * @property string $role
     */
    final class GateUser extends Model
    {
        use Authorizable;

        protected static string $table = 'users';

        protected static array $fillable = ['email'];
    }

    /**
     * @property int $id
     * @property int $user_id
     * @property string $titulo
     */
    final class GateNota extends Model
    {
        protected static string $table = 'notas';

        protected static array $fillable = ['user_id', 'titulo'];
    }

    final class GateSinPolitica extends Model
    {
        protected static string $table = 'notas';
    }
}

namespace App\Policies {
    use GateNota;
    use GateUser;

    final class GateNotaPolicy
    {
        public function create(GateUser $user): bool
        {
            return $user->role !== 'invitado';
        }

        public function update(GateUser $user, GateNota $nota): bool
        {
            return $nota->user_id === $user->id;
        }
    }
}
