<?php

namespace Database\Factories;

use App\Enums\EstadoUsuario;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /** Contraseña de las cuentas de prueba creadas por la factory. */
    public const CONTRASENA_PRUEBA = 'Clave-Prueba-123';

    public function definition(): array
    {
        return [
            'nombres' => fake()->name(),
            'documento' => (string) fake()->unique()->numberBetween(10000000, 1999999999),
            'correo' => fake()->unique()->safeEmail(),
            'contrasena_cifrada' => self::CONTRASENA_PRUEBA,
            'estado' => EstadoUsuario::ACTIVO,
            'id_rol' => Rol::factory(),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['estado' => EstadoUsuario::INACTIVO]);
    }

    public function conRol(string $nombreRol): static
    {
        return $this->state(fn () => [
            'id_rol' => Rol::where('nombre_rol', $nombreRol)->value('id_rol'),
        ]);
    }
}
