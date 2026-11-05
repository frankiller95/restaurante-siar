<?php

namespace Tests\Feature\Pedidos;

use App\Enums\EstadoPedido;
use App\Enums\TipoEvento;
use App\Events\PedidoCancelado;
use App\Events\PedidoEnviadoACocina;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** RF20 Cancelar, RF21 Enviar a cocina y RF22 Consultar estado. */
class FlujoPedidoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $mesero;

    private Usuario $cocinero;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();

        // Solo se simulan los eventos de dominio; la bitácora sigue funcionando.
        Event::fake([PedidoEnviadoACocina::class, PedidoCancelado::class]);

        $this->mesero = $this->crearUsuario(Rol::MESERO);
        $this->cocinero = $this->crearUsuario(Rol::COCINERO);
        $this->producto = Producto::factory()->create(['nombre' => 'Ajiaco', 'precio' => 24000]);
    }

    private function pedidoConProducto(): Pedido
    {
        $pedido = Pedido::factory()->create(['id_usuario' => $this->mesero->id_usuario]);
        DetallePedido::factory()->create([
            'id_pedido' => $pedido->id_pedido,
            'id_producto' => $this->producto->id_producto,
            'precio_unitario' => 24000,
        ]);

        return $pedido;
    }

    // --- RF21 Enviar a cocina y flujo completo -------------------------------

    public function test_flujo_completo_de_un_pedido(): void
    {
        $pedido = $this->pedidoConProducto();
        $url = "/api/pedidos/{$pedido->id_pedido}";

        $this->comoUsuario($this->mesero)
            ->patchJson("{$url}/enviar-cocina")
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.enviado_cocina', true)
            ->assertJsonPath('data.editable', false);
        Event::assertDispatched(PedidoEnviadoACocina::class, fn ($e) => $e->pedido->is($pedido));

        $this->comoUsuario($this->cocinero);
        $this->getJson('/api/pedidos/cocina')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id_pedido', $pedido->id_pedido);
        $this->patchJson("{$url}/preparar")->assertOk()->assertJsonPath('data.estado', 'en_preparacion');
        $this->patchJson("{$url}/listo")->assertOk()->assertJsonPath('data.estado', 'listo');

        $this->comoUsuario($this->mesero)
            ->patchJson("{$url}/entregar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'entregado')
            ->assertJsonPath('data.acciones', ['facturado']);

        $pedido->refresh();
        $this->assertNotNull($pedido->fecha_envio_cocina);
        $this->assertNotNull($pedido->fecha_inicio_preparacion);
        $this->assertNotNull($pedido->fecha_listo);
        $this->assertNotNull($pedido->fecha_entrega);

        // Ya entregado, sale de la cola de cocina.
        $this->comoUsuario($this->cocinero)->getJson('/api/pedidos/cocina')->assertOk()->assertJsonCount(0);

        foreach ([TipoEvento::PEDIDO_ENVIADO_COCINA, TipoEvento::PEDIDO_EN_PREPARACION, TipoEvento::PEDIDO_LISTO, TipoEvento::PEDIDO_ENTREGADO] as $tipo) {
            $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => $tipo->value]);
        }
    }

    public function test_no_envia_a_cocina_un_pedido_vacio(): void
    {
        $pedido = Pedido::factory()->create();

        $this->comoUsuario($this->mesero)
            ->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")
            ->assertStatus(422);

        Event::assertNotDispatched(PedidoEnviadoACocina::class);
    }

    public function test_no_envia_a_cocina_si_un_producto_se_agoto(): void
    {
        $pedido = $this->pedidoConProducto();
        $this->producto->marcarDisponible(false);

        $this->comoUsuario($this->mesero)
            ->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'productos_no_disponibles');

        $this->assertNull($pedido->fresh()->fecha_envio_cocina);
    }

    public function test_el_cocinero_no_prepara_lo_que_no_se_ha_enviado(): void
    {
        $pedido = $this->pedidoConProducto();

        $this->comoUsuario($this->cocinero)
            ->patchJson("/api/pedidos/{$pedido->id_pedido}/preparar")
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'transicion_invalida');
    }

    public function test_transiciones_invalidas(): void
    {
        $pedido = $this->pedidoConProducto();

        // No se entrega lo que no está listo.
        $this->comoUsuario($this->mesero)
            ->patchJson("/api/pedidos/{$pedido->id_pedido}/entregar")
            ->assertStatus(409);

        // No se marca listo lo que no se está preparando.
        $pedido->enviarACocina();
        $this->comoUsuario($this->cocinero)
            ->patchJson("/api/pedidos/{$pedido->id_pedido}/listo")
            ->assertStatus(409);
    }

    // --- RF20 Cancelar ---------------------------------------------------------

    public function test_cancela_un_pedido_pendiente(): void
    {
        $pedido = $this->pedidoConProducto();

        $this->comoUsuario($this->mesero)
            ->patchJson("/api/pedidos/{$pedido->id_pedido}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado');

        Event::assertDispatched(PedidoCancelado::class, fn ($e) => ! $e->fueEnviadoACocina && ! $e->estabaEnPreparacion);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::PEDIDO_CANCELADO->value]);
    }

    public function test_en_preparacion_se_cancela_solo_con_motivo(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::EN_PREPARACION)->create();
        $url = "/api/pedidos/{$pedido->id_pedido}/cancelar";

        $this->comoUsuario($this->mesero);
        $this->patchJson($url)->assertStatus(422)->assertJsonValidationErrors('motivo');

        $this->patchJson($url, ['motivo' => 'El cliente se fue'])
            ->assertOk()
            ->assertJsonPath('data.motivo_cancelacion', 'El cliente se fue');

        Event::assertDispatched(PedidoCancelado::class, fn ($e) => $e->fueEnviadoACocina && $e->estabaEnPreparacion);
    }

    public function test_no_se_cancela_un_pedido_listo_o_entregado(): void
    {
        $listo = Pedido::factory()->enEstado(EstadoPedido::LISTO)->create();
        $entregado = Pedido::factory()->enEstado(EstadoPedido::ENTREGADO)->create();

        $this->comoUsuario($this->mesero);
        $this->patchJson("/api/pedidos/{$listo->id_pedido}/cancelar", ['motivo' => 'x'])->assertStatus(409);
        $this->patchJson("/api/pedidos/{$entregado->id_pedido}/cancelar", ['motivo' => 'x'])->assertStatus(409);
    }

    // --- RF22 Consultar estado -------------------------------------------------

    public function test_consulta_y_filtra_pedidos(): void
    {
        $mesa = Mesa::factory()->create();
        Pedido::factory()->create(['id_mesa' => $mesa->id_mesa, 'id_usuario' => $this->mesero->id_usuario]);
        Pedido::factory()->enEstado(EstadoPedido::LISTO)->create();
        Pedido::factory()->enEstado(EstadoPedido::CANCELADO)->create();

        $this->comoUsuario($this->mesero);
        $this->getJson('/api/pedidos')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/pedidos?estado=abiertos')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/pedidos?estado=listo')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/pedidos?id_mesa={$mesa->id_mesa}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/pedidos?mios=1')->assertOk()->assertJsonCount(1, 'data');

        $hoy = now(config('siar.zona_horaria'))->toDateString();
        $ayer = now(config('siar.zona_horaria'))->subDay()->toDateString();
        $this->getJson("/api/pedidos?fecha={$hoy}")->assertOk()->assertJsonCount(3, 'data');
        $this->getJson("/api/pedidos?fecha={$ayer}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_solo_cocina_ve_la_cola_y_prepara(): void
    {
        $pedido = Pedido::factory()->enviado()->create();

        $this->comoUsuario($this->mesero);
        $this->getJson('/api/pedidos/cocina')->assertForbidden();
        $this->patchJson("/api/pedidos/{$pedido->id_pedido}/preparar")->assertForbidden();
    }
}
