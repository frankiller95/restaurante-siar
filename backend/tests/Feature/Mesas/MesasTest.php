<?php

namespace Tests\Feature\Mesas;

use App\Enums\EstadoMesa;
use App\Enums\TipoEvento;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** RF12 Crear, RF13 Modificar, RF14 Consultar estado y RF16 Liberar mesas. */
class MesasTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
    }

    // --- RF12 Crear mesas ----------------------------------------------------

    public function test_el_administrador_crea_una_mesa_libre(): void
    {
        $this->comoUsuario($this->admin)
            ->postJson('/api/mesas', ['numero' => 5, 'capacidad' => 4, 'ubicacion' => 'Terraza'])
            ->assertCreated()
            ->assertJsonPath('data.numero', 5)
            ->assertJsonPath('data.estado', 'libre')
            ->assertJsonPath('data.acciones', ['ocupar', 'reservar', 'enviarAMantenimiento']);

        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::MESA_CREADA->value]);
    }

    public function test_valida_numero_unico_y_capacidad(): void
    {
        Mesa::factory()->create(['numero' => 5]);

        $this->comoUsuario($this->admin)
            ->postJson('/api/mesas', ['numero' => 5, 'capacidad' => 4])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero');

        $this->postJson('/api/mesas', ['numero' => 6, 'capacidad' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('capacidad');

        $this->postJson('/api/mesas', ['numero' => 6, 'capacidad' => 31])
            ->assertStatus(422)
            ->assertJsonValidationErrors('capacidad');
    }

    // --- RF13 Modificar mesas ------------------------------------------------

    public function test_modifica_una_mesa(): void
    {
        $mesa = Mesa::factory()->create(['numero' => 1, 'capacidad' => 2]);

        $this->comoUsuario($this->admin)
            ->putJson("/api/mesas/{$mesa->id_mesa}", ['numero' => 10, 'capacidad' => 6, 'ubicacion' => 'Salón'])
            ->assertOk()
            ->assertJsonPath('data.numero', 10)
            ->assertJsonPath('data.capacidad', 6);
    }

    public function test_la_capacidad_no_baja_de_una_reserva_pendiente(): void
    {
        $mesa = Mesa::factory()->create(['numero' => 1, 'capacidad' => 6]);
        Reserva::factory()->create(['id_mesa' => $mesa->id_mesa, 'cantidad_personas' => 5, 'id_usuario' => $this->admin->id_usuario]);

        $this->comoUsuario($this->admin)
            ->putJson("/api/mesas/{$mesa->id_mesa}", ['numero' => 1, 'capacidad' => 4])
            ->assertStatus(422)
            ->assertJsonValidationErrors('capacidad');
    }

    public function test_da_de_baja_una_mesa_libre_sin_reservas(): void
    {
        $libre = Mesa::factory()->create();
        $ocupada = Mesa::factory()->enEstado(EstadoMesa::OCUPADA)->create();

        $this->comoUsuario($this->admin);
        $this->deleteJson("/api/mesas/{$ocupada->id_mesa}")->assertStatus(409);
        $this->deleteJson("/api/mesas/{$libre->id_mesa}")->assertOk();

        $this->assertDatabaseHas('mesas', ['id_mesa' => $libre->id_mesa, 'activo' => false]);
        $this->getJson('/api/mesas')->assertOk()->assertJsonCount(1, 'data');
    }

    // --- RF14 Consultar estado -----------------------------------------------

    public function test_consulta_el_estado_de_las_mesas_con_resumen(): void
    {
        Mesa::factory()->create(['numero' => 1]);
        Mesa::factory()->enEstado(EstadoMesa::OCUPADA)->create(['numero' => 2]);
        Mesa::factory()->enEstado(EstadoMesa::MANTENIMIENTO)->create(['numero' => 3]);

        $this->comoUsuario($this->crearUsuario(Rol::MESERO))
            ->getJson('/api/mesas')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.numero', 1)
            ->assertJsonPath('resumen.libre', 1)
            ->assertJsonPath('resumen.ocupada', 1)
            ->assertJsonPath('resumen.mantenimiento', 1)
            ->assertJsonPath('resumen.reservada', 0);

        $this->getJson('/api/mesas?estado=ocupada')->assertOk()->assertJsonCount(1, 'data');
    }

    // --- Transiciones (patrón State) y RF16 Liberar --------------------------

    public function test_ocupar_y_liberar_una_mesa(): void
    {
        $mesa = Mesa::factory()->create(['numero' => 7]);
        $this->comoUsuario($this->crearUsuario(Rol::MESERO));

        $this->patchJson("/api/mesas/{$mesa->id_mesa}/ocupar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'ocupada')
            ->assertJsonPath('data.acciones', ['liberar']);

        $this->patchJson("/api/mesas/{$mesa->id_mesa}/liberar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'libre');

        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::MESA_ESTADO_CAMBIADO->value]);
    }

    public function test_transicion_invalida_responde_409(): void
    {
        $mesa = Mesa::factory()->create();

        $this->comoUsuario($this->admin)
            ->patchJson("/api/mesas/{$mesa->id_mesa}/liberar")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'transicion_invalida');

        $this->patchJson("/api/mesas/{$mesa->id_mesa}/habilitar")->assertStatus(409);
    }

    public function test_mantenimiento_y_habilitar(): void
    {
        $mesa = Mesa::factory()->create();
        $this->comoUsuario($this->admin);

        $this->patchJson("/api/mesas/{$mesa->id_mesa}/mantenimiento")->assertOk()->assertJsonPath('data.estado', 'mantenimiento');
        $this->patchJson("/api/mesas/{$mesa->id_mesa}/ocupar")->assertStatus(409);
        $this->patchJson("/api/mesas/{$mesa->id_mesa}/habilitar")->assertOk()->assertJsonPath('data.estado', 'libre');
    }

    public function test_no_libera_una_mesa_con_pedidos_abiertos(): void
    {
        // Simula la tabla del Módulo 4 (Pedidos).
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id('id_pedido');
            $table->unsignedBigInteger('id_mesa');
            $table->string('estado');
        });

        $mesa = Mesa::factory()->enEstado(EstadoMesa::OCUPADA)->create();
        DB::table('pedidos')->insert(['id_mesa' => $mesa->id_mesa, 'estado' => 'entregado']);

        $this->comoUsuario($this->admin)
            ->patchJson("/api/mesas/{$mesa->id_mesa}/liberar")
            ->assertStatus(409);

        DB::table('pedidos')->update(['estado' => 'facturado']);
        $this->patchJson("/api/mesas/{$mesa->id_mesa}/liberar")->assertOk();
    }

    // --- Permisos ------------------------------------------------------------

    public function test_permisos_del_modulo_de_mesas(): void
    {
        $mesa = Mesa::factory()->create();

        // El mesero consulta y ocupa, pero no crea ni envía a mantenimiento.
        $this->comoUsuario($this->crearUsuario(Rol::MESERO));
        $this->postJson('/api/mesas', ['numero' => 50, 'capacidad' => 2])->assertForbidden();
        $this->patchJson("/api/mesas/{$mesa->id_mesa}/mantenimiento")->assertForbidden();

        // El cocinero no tiene acceso a mesas.
        $this->comoUsuario($this->crearUsuario(Rol::COCINERO));
        $this->getJson('/api/mesas')->assertForbidden();
    }
}
