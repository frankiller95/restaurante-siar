<?php

namespace Database\Factories;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rol>
 */
class RolFactory extends Factory
{
    protected $model = Rol::class;

    public function definition(): array
    {
        return [
            'nombre_rol' => 'Rol '.fake()->unique()->word(),
            'descripcion' => fake()->sentence(),
            'es_predefinido' => false,
        ];
    }
}
