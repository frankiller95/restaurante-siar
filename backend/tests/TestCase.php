<?php

namespace Tests;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\PermisoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Carga los permisos y los 4 roles del sistema. */
    protected function sembrarRolesYPermisos(): void
    {
        $this->seed([PermisoSeeder::class, RolSeeder::class]);
    }

    protected function crearUsuario(string $rol = Rol::ADMINISTRADOR, array $atributos = []): Usuario
    {
        return Usuario::factory()->conRol($rol)->create($atributos);
    }

    /** Inicia sesión con un token real de Sanctum y lo envía en las siguientes peticiones. */
    protected function comoUsuario(Usuario $usuario): static
    {
        $token = $usuario->createToken('pruebas')->plainTextToken;
        $this->refrescarAutenticacion();

        return $this->withToken($token);
    }

    /** Olvida el usuario autenticado en memoria (entre peticiones de una misma prueba). */
    protected function refrescarAutenticacion(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
