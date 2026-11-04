<?php

namespace Database\Factories;

use App\Enums\EstadoMesa;
use App\Models\Mesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mesa>
 */
class MesaFactory extends Factory
{
    protected $model = Mesa::class;

    public function definition(): array
    {
        return [
            'numero' => fake()->unique()->numberBetween(1, 999),
            'capacidad' => fake()->randomElement([2, 4, 6]),
            'ubicacion' => fake()->randomElement(['Salón', 'Terraza', 'Segundo piso']),
        ];
    }

    public function enEstado(EstadoMesa $estado): static
    {
        return $this->afterCreating(fn (Mesa $mesa) => $mesa->forceFill(['estado' => $estado])->save());
    }
}
