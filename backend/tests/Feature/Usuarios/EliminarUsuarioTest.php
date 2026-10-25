<?php

namespace Tests\Feature\Usuarios;

use App\Enums\TipoEvento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** CU-03 Eliminar usuario. */
class EliminarUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
        Mail::fake();
    }

    public function test_sin_historial_se_borra_el_registro(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);
        $mesero->createToken('turno');

        $this->comoUsuario($this->admin)
            ->deleteJson("/api/usuarios/{$mesero->id_usuario}")
            ->assertOk()
            ->assertJsonPath('tipo_eliminacion', 'fisica');

        $this->assertDatabaseMissing('usuarios', ['id_usuario' => $mesero->id_usuario]);
        $this->assertDatabaseMissing('sesiones', ['tokenable_id' => $mesero->id_usuario]);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::USUARIO_ELIMINADO->value]);
    }

    public function test_con_historial_se_inactiva_y_se_conserva(): void
    {
        // Simula la tabla de reservas del módulo 3.
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_usuario');
        });

        $mesero = $this->crearUsuario(Rol::MESERO);
        $mesero->createToken('turno');
        DB::table('reservas')->insert(['id_usuario' => $mesero->id_usuario]);

        $this->comoUsuario($this->admin)
            ->deleteJson("/api/usuarios/{$mesero->id_usuario}")
            ->assertOk()
            ->assertJsonPath('tipo_eliminacion', 'logica');

        $this->assertDatabaseHas('usuarios', ['id_usuario' => $mesero->id_usuario, 'estado' => 'inactivo']);
        $this->assertSame(0, $mesero->tokens()->count());
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::USUARIO_INACTIVADO->value]);

        // Un usuario ya inactivo con historial no se puede volver a eliminar.
        $this->deleteJson("/api/usuarios/{$mesero->id_usuario}")->assertStatus(422);
    }

    public function test_el_administrador_no_puede_eliminarse_a_si_mismo(): void
    {
        $this->comoUsuario($this->admin)
            ->deleteJson("/api/usuarios/{$this->admin->id_usuario}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No puedes eliminar tu propia cuenta.');

        $this->assertDatabaseHas('usuarios', ['id_usuario' => $this->admin->id_usuario]);
    }

    public function test_la_bitacora_se_conserva_al_borrar_el_usuario(): void
    {
        $mesero = $this->crearUsuario(Rol::MESERO);
        $mesero->bitacora()->create(['tipo_evento' => 'inicio_sesion', 'descripcion' => 'Prueba', 'fecha_hora' => now()]);

        $this->comoUsuario($this->admin)->deleteJson("/api/usuarios/{$mesero->id_usuario}")->assertOk();

        $this->assertDatabaseHas('bitacora_auditoria', ['descripcion' => 'Prueba', 'id_usuario' => null]);
    }
}
