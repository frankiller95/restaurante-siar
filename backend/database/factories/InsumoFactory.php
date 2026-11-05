<?php

namespace Database\Factories;

use App\Enums\UnidadMedida;
use App\Models\Insumo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Insumo>
 */
class InsumoFactory extends Factory
{
    protected $model = Insumo::class;

    public function definition(): array
    {
        return [
            'nombre' => ucfirst(fake()->unique()->words(2, true)),
            'unidad_medida' => UnidadMedida::KILOGRAMO,
            'stock_minimo' => 1,
            'costo_unitario' => 5000,
        ];
    }

    /** Stock inicial (sin registrar movimiento; para pruebas). */
    public function conStock(float $stock, float $minimo = 1): static
    {
        return $this->afterCreating(fn (Insumo $insumo) => $insumo->forceFill([
            'stock_actual' => $stock,
            'stock_minimo' => $minimo,
        ])->save());
    }
}
