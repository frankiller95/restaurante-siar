<?php

namespace Tests\Feature\Mesas;

use App\Enums\EstadoMesa;
use App\Enums\TipoEvento;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** RF15 Reservar mesas. */
class ReservasTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $mesero;

    private Mesa $mesa;

    /** "Ahora" fijo en la hora del restaurante: martes 10 de noviembre, 3:00 p. m. */
    private Carbon $ahora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();

        $this->ahora = Carbon::parse('2026-11-10 15:00', 'America/Bogota');
        $this->travelTo($this->ahora);

        $this->mesero = $this->crearUsuario(Rol::MESERO);
        $this->mesa = Mesa::factory()->create(['numero' => 4, 'capacidad' => 4]);
    }

    private function datos(array $cambios = []): array
    {
        return [
            'fecha' => '2026-11-10',
            'hora' => '20:00',
            'cantidad_personas' => 3,
            'id_mesa' => $this->mesa->id_mesa,
            'cliente_nombre' => 'Camila Rojas',
            'cliente_telefono' => '300 123 4567',
            ...$cambios,
        ];
    }

    public function test_el_mesero_registra_una_reserva_a_nombre_del_cliente(): void
    {
        $this->comoUsuario($this->mesero)
            ->postJson('/api/reservas', $this->datos(['observaciones' => 'Cumpleaños']))
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.cliente_telefono', '3001234567')
            ->assertJsonPath('data.mesa.numero', 4)
            ->assertJsonPath('data.registrada_por', $this->mesero->nombres);

        // Es para dentro de 5 h: la mesa sigue libre por ahora.
        $this->assertSame(EstadoMesa::LIBRE, $this->mesa->fresh()->estado);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::RESERVA_CREADA->value]);
    }

    public function test_valida_capacidad_horario_futuro_y_datos_del_cliente(): void
    {
        $this->comoUsuario($this->mesero);

        $this->postJson('/api/reservas', $this->datos(['cantidad_personas' => 6]))
            ->assertStatus(422)->assertJsonValidationErrors('cantidad_personas');

        $this->postJson('/api/reservas', $this->datos(['hora' => '14:00']))
            ->assertStatus(422)->assertJsonValidationErrors('hora');

        $this->postJson('/api/reservas', $this->datos(['cliente_nombre' => '', 'cliente_telefono' => 'abc']))
            ->assertStatus(422)->assertJsonValidationErrors(['cliente_nombre', 'cliente_telefono']);
    }

    public function test_no_reserva_una_mesa_en_mantenimiento(): void
    {
        $this->mesa->enviarAMantenimiento();

        $this->comoUsuario($this->mesero)
            ->postJson('/api/reservas', $this->datos())
            ->assertStatus(422)->assertJsonValidationErrors('id_mesa');
    }

    public function test_no_permite_reservas_que_se_crucen_en_la_misma_mesa(): void
    {
        $this->comoUsuario($this->mesero);
        $this->postJson('/api/reservas', $this->datos(['hora' => '19:00']))->assertCreated();

        $this->postJson('/api/reservas', $this->datos(['hora' => '20:30']))
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'cruce_reserva');

        // A las 21:00 ya pasaron las 2 h de la primera.
        $this->postJson('/api/reservas', $this->datos(['hora' => '21:00']))->assertCreated();
    }

    public function test_mesas_disponibles_para_un_horario(): void
    {
        $grande = Mesa::factory()->create(['numero' => 8, 'capacidad' => 8]);
        Mesa::factory()->create(['numero' => 2, 'capacidad' => 2]);
        Mesa::factory()->enEstado(EstadoMesa::MANTENIMIENTO)->create(['numero' => 9, 'capacidad' => 6]);
        Reserva::factory()->para(Carbon::parse('2026-11-10 19:30', 'America/Bogota'))
            ->create(['id_mesa' => $this->mesa->id_mesa, 'id_usuario' => $this->mesero->id_usuario]);

        $respuesta = $this->comoUsuario($this->mesero)
            ->getJson('/api/mesas/disponibles?fecha=2026-11-10&hora=20:00&personas=3')
            ->assertOk();

        $this->assertSame([$grande->numero], array_column($respuesta->json(), 'numero'));
    }

    public function test_la_mesa_pasa_a_reservada_una_hora_antes(): void
    {
        $this->comoUsuario($this->mesero)->postJson('/api/reservas', $this->datos(['hora' => '17:00']))->assertCreated();
        $this->assertSame(EstadoMesa::LIBRE, $this->mesa->fresh()->estado);

        // 4:10 p. m.: faltan 50 minutos.
        $this->travelTo($this->ahora->copy()->addMinutes(70));

        $this->getJson('/api/mesas')
            ->assertOk()
            ->assertJsonPath('data.0.estado', 'reservada')
            ->assertJsonPath('data.0.proxima_reserva.cliente_nombre', 'Camila Rojas')
            ->assertJsonPath('resumen.reservada', 1);
    }

    public function test_una_reserva_cercana_aparta_la_mesa_de_inmediato(): void
    {
        $this->comoUsuario($this->mesero)->postJson('/api/reservas', $this->datos(['hora' => '15:30']))->assertCreated();

        $this->assertSame(EstadoMesa::RESERVADA, $this->mesa->fresh()->estado);
        // Una mesa reservada no se ocupa con otro cliente ni se libera directamente.
        $this->patchJson("/api/mesas/{$this->mesa->id_mesa}/ocupar")->assertStatus(409);
        $this->patchJson("/api/mesas/{$this->mesa->id_mesa}/liberar")->assertStatus(409);
    }

    public function test_registrar_llegada_ocupa_la_mesa(): void
    {
        $id = $this->comoUsuario($this->mesero)
            ->postJson('/api/reservas', $this->datos(['hora' => '15:30']))
            ->json('data.id_reserva');

        $this->patchJson("/api/reservas/{$id}/llegada")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cumplida');

        $this->assertSame(EstadoMesa::OCUPADA, $this->mesa->fresh()->estado);
        $this->patchJson("/api/reservas/{$id}/llegada")->assertStatus(422);
    }

    public function test_cancelar_libera_la_mesa_reservada(): void
    {
        $id = $this->comoUsuario($this->mesero)
            ->postJson('/api/reservas', $this->datos(['hora' => '15:30']))
            ->json('data.id_reserva');
        $this->assertSame(EstadoMesa::RESERVADA, $this->mesa->fresh()->estado);

        $this->patchJson("/api/reservas/{$id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada');

        $this->assertSame(EstadoMesa::LIBRE, $this->mesa->fresh()->estado);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::RESERVA_CANCELADA->value]);
    }

    public function test_si_el_cliente_no_llega_la_reserva_vence_y_la_mesa_se_libera(): void
    {
        $id = $this->comoUsuario($this->mesero)
            ->postJson('/api/reservas', $this->datos(['hora' => '15:30']))
            ->json('data.id_reserva');

        // 3:51 p. m.: pasaron los 20 minutos de tolerancia.
        $this->travelTo($this->ahora->copy()->addMinutes(51));

        $this->getJson('/api/reservas?fecha=2026-11-10')
            ->assertOk()
            ->assertJsonPath('data.0.id_reserva', $id)
            ->assertJsonPath('data.0.estado', 'no_asistio');

        $this->assertSame(EstadoMesa::LIBRE, $this->mesa->fresh()->estado);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::RESERVA_NO_ASISTIO->value]);
    }

    public function test_lista_y_filtra_reservas(): void
    {
        $this->comoUsuario($this->mesero);
        $this->postJson('/api/reservas', $this->datos(['hora' => '19:00']))->assertCreated();
        $this->postJson('/api/reservas', $this->datos(['fecha' => '2026-11-11', 'hora' => '13:00', 'cliente_nombre' => 'Andrés Gil']))->assertCreated();

        $this->getJson('/api/reservas')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/reservas?fecha=2026-11-11')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cliente_nombre', 'Andrés Gil');
        $this->getJson('/api/reservas?buscar=camila')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_cocinero_no_gestiona_reservas(): void
    {
        $this->comoUsuario($this->crearUsuario(Rol::COCINERO))
            ->postJson('/api/reservas', $this->datos())
            ->assertForbidden();
    }
}
