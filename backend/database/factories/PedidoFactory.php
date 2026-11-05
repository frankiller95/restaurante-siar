<?php

namespace Database\Factories;

use App\Enums\EstadoPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    protected $model = Pedido::class;

    public function definition(): array
    {
        return [
            'id_mesa' => Mesa::factory(),
            'id_usuario' => Usuario::factory(),
            'observaciones' => null,
        ];
    }

    public function enEstado(EstadoPedido $estado, bool $enviado = true): static
    {
        return $this->afterCreating(fn (Pedido $pedido) => $pedido->forceFill([
            'estado' => $estado,
            'fecha_envio_cocina' => $enviado ? now() : null,
        ])->save());
    }

    public function enviado(): static
    {
        return $this->afterCreating(fn (Pedido $pedido) => $pedido->forceFill(['fecha_envio_cocina' => now()])->save());
    }
}
