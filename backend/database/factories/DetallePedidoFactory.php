<?php

namespace Database\Factories;

use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetallePedido>
 */
class DetallePedidoFactory extends Factory
{
    protected $model = DetallePedido::class;

    public function definition(): array
    {
        return [
            'id_pedido' => Pedido::factory(),
            'id_producto' => Producto::factory(),
            'cantidad' => 1,
            'precio_unitario' => 15000,
        ];
    }
}
