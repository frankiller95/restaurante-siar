<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst(fake()->unique()->words(2, true)),
            'descripcion' => fake()->sentence(),
            'precio' => fake()->randomElement([8000, 12500, 18900, 25000, 32000]),
            'disponible' => true,
            'id_categoria' => Categoria::factory(),
        ];
    }

    public function agotado(): static
    {
        return $this->state(fn () => ['disponible' => false]);
    }

    public function dadoDeBaja(): static
    {
        return $this->afterCreating(fn (Producto $producto) => $producto->darDeBaja());
    }
}
