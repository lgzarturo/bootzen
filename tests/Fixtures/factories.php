<?php

/**
 * Modelos y factories de prueba para FactoryTest (las factories viven en Database\Factories).
 */

declare(strict_types=1);

namespace {
    use BootZen\Database\Model;

    /**
     * @property int $id
     * @property string $nombre
     * @property string $email
     * @property string $role
     */
    final class FactoryCliente extends Model
    {
        protected static string $table = 'clientes';

        protected static array $fillable = ['nombre', 'email'];
    }

    /**
     * @property int $id
     * @property int $cliente_id
     * @property string $titulo
     * @property bool $pagado
     * @property float $total
     */
    final class FactoryPedido extends Model
    {
        protected static string $table = 'pedidos';

        protected static array $fillable = ['titulo'];

        protected static array $casts = ['pagado' => 'bool', 'total' => 'float'];

        protected static bool $timestamps = false;
    }

    final class FactorySinFactory extends Model
    {
        protected static string $table = 'clientes';
    }
}

namespace Database\Factories {
    use BootZen\Database\Factory;
    use FactoryCliente;
    use FactoryPedido;

    /**
     * @extends Factory<FactoryCliente>
     */
    final class FactoryClienteFactory extends Factory
    {
        protected string $model = FactoryCliente::class;

        public function definition(): array
        {
            return ['nombre' => fake()->name(), 'email' => fake()->unique()->email(), 'role' => 'cliente'];
        }
    }

    /**
     * @extends Factory<FactoryPedido>
     */
    final class FactoryPedidoFactory extends Factory
    {
        protected string $model = FactoryPedido::class;

        public function definition(): array
        {
            return [
                'cliente_id' => FactoryCliente::factory(),
                'titulo' => fake()->sentence(),
                'pagado' => false,
                'total' => fn (array $valores): float => $valores['pagado'] ? 100.0 : 50.0,
            ];
        }
    }
}
