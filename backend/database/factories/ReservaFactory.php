<?php

namespace Database\Factories;

use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
{
    protected $model = Reserva::class;

    public function definition(): array
    {
        $inicio = now(config('siar.zona_horaria'))->addDays(2)->setTime(19, 0);

        return [
            'fecha' => $inicio->format('Y-m-d'),
            'hora' => $inicio->format('H:i'),
            'cantidad_personas' => 2,
            'cliente_nombre' => fake()->name(),
            'cliente_telefono' => '3'.fake()->numerify('#########'),
            'id_usuario' => Usuario::factory(),
            'id_mesa' => Mesa::factory(),
        ];
    }

    /** Reserva que empieza en el instante indicado (hora del restaurante). */
    public function para(Carbon $inicio): static
    {
        $local = $inicio->copy()->setTimezone(config('siar.zona_horaria'));

        return $this->state(fn () => ['fecha' => $local->format('Y-m-d'), 'hora' => $local->format('H:i')]);
    }
}
