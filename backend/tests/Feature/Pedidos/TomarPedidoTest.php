<?php

namespace Tests\Feature\Pedidos;

use App\Enums\EstadoMesa;
use App\Enums\TipoEvento;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF17 Crear pedido, RF18 Agregar productos y RF19 Modificar pedido. */
class TomarPedidoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $mesero;

    private Mesa $mesa;

    private Producto $bandeja;

    private Producto $limonada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();

        $this->mesero = $this->crearUsuario(Rol::MESERO);
        $this->mesa = Mesa::factory()->create(['numero' => 3]);
        $this->bandeja = Producto::factory()->create(['nombre' => 'Bandeja paisa', 'precio' => 32000]);
        $this->limonada = Producto::factory()->create(['nombre' => 'Limonada', 'precio' => 8000]);
    }

    private function crearPedido(array $productos = []): array
    {
        return $this->comoUsuario($this->mesero)
            ->postJson('/api/pedidos', ['id_mesa' => $this->mesa->id_mesa, 'productos' => $productos])
            ->assertCreated()
            ->json('data');
    }

    // --- RF17 Crear pedido ---------------------------------------------------

    public function test_crea_un_pedido_y_ocupa_la_mesa_libre(): void
    {
        $pedido = $this->crearPedido([
            ['id_producto' => $this->bandeja->id_producto, 'cantidad' => 2],
            ['id_producto' => $this->limonada->id_producto, 'cantidad' => 1, 'observacion' => 'sin hielo'],
        ]);

        $this->assertSame('pendiente', $pedido['estado']);
        $this->assertTrue($pedido['editable']);
        $this->assertFalse($pedido['enviado_cocina']);
        $this->assertSame(3, $pedido['mesa']['numero']);
        $this->assertSame($this->mesero->nombres, $pedido['mesero']);
        $this->assertCount(2, $pedido['detalles']);
        $this->assertEquals(72000, $pedido['total']);
        $this->assertSame('sin hielo', $pedido['detalles'][1]['observacion']);

        $this->assertSame(EstadoMesa::OCUPADA, $this->mesa->fresh()->estado);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::PEDIDO_CREADO->value]);
    }

    public function test_una_mesa_ocupada_puede_tener_varios_pedidos(): void
    {
        $this->crearPedido();
        $this->crearPedido();

        $this->getJson("/api/pedidos?id_mesa={$this->mesa->id_mesa}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_no_crea_pedidos_en_mesas_reservadas_o_en_mantenimiento(): void
    {
        $reservada = Mesa::factory()->enEstado(EstadoMesa::RESERVADA)->create();
        $mantenimiento = Mesa::factory()->enEstado(EstadoMesa::MANTENIMIENTO)->create();

        $this->comoUsuario($this->mesero);
        $this->postJson('/api/pedidos', ['id_mesa' => $reservada->id_mesa])->assertStatus(409);
        $this->postJson('/api/pedidos', ['id_mesa' => $mantenimiento->id_mesa])->assertStatus(409);

        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_no_agrega_productos_agotados_ni_dados_de_baja(): void
    {
        $agotado = Producto::factory()->agotado()->create(['nombre' => 'Sancocho']);
        $baja = Producto::factory()->dadoDeBaja()->create();

        $this->comoUsuario($this->mesero)
            ->postJson('/api/pedidos', [
                'id_mesa' => $this->mesa->id_mesa,
                'productos' => [['id_producto' => $agotado->id_producto, 'cantidad' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_producto');

        $pedido = $this->crearPedido();
        $this->postJson("/api/pedidos/{$pedido['id_pedido']}/productos", ['id_producto' => $baja->id_producto, 'cantidad' => 1])
            ->assertStatus(422);

        // La creación fallida no dejó el pedido ni ocupó la mesa a medias.
        $this->assertDatabaseCount('pedidos', 1);
    }

    // --- RF18 Agregar productos ----------------------------------------------

    public function test_agrega_productos_y_suma_las_lineas_iguales(): void
    {
        $pedido = $this->crearPedido();
        $url = "/api/pedidos/{$pedido['id_pedido']}/productos";

        $this->postJson($url, ['id_producto' => $this->limonada->id_producto, 'cantidad' => 1])->assertOk();
        $this->postJson($url, ['id_producto' => $this->limonada->id_producto, 'cantidad' => 2])->assertOk();
        $respuesta = $this->postJson($url, ['id_producto' => $this->limonada->id_producto, 'cantidad' => 1, 'observacion' => 'sin azúcar'])
            ->assertOk();

        $respuesta->assertJsonCount(2, 'data.detalles')
            ->assertJsonPath('data.detalles.0.cantidad', 3)
            ->assertJsonPath('data.cantidad_productos', 4);
        $this->assertEquals(32000, $respuesta->json('data.total'));
    }

    public function test_el_precio_queda_fijo_aunque_cambie_en_el_menu(): void
    {
        $pedido = $this->crearPedido([['id_producto' => $this->bandeja->id_producto, 'cantidad' => 1]]);

        $this->bandeja->update(['precio' => 40000]);

        $this->getJson("/api/pedidos/{$pedido['id_pedido']}")
            ->assertOk()
            ->assertJsonPath('detalles.0.precio_unitario', 32000)
            ->assertJsonPath('total', 32000);

        // Si se pide de nuevo, la línea nueva usa el precio nuevo (no se mezcla).
        $this->postJson("/api/pedidos/{$pedido['id_pedido']}/productos", ['id_producto' => $this->bandeja->id_producto, 'cantidad' => 1])
            ->assertOk()
            ->assertJsonCount(2, 'data.detalles')
            ->assertJsonPath('data.total', 72000);
    }

    // --- RF19 Modificar pedido -----------------------------------------------

    public function test_modifica_y_quita_productos_mientras_esta_pendiente(): void
    {
        $pedido = $this->crearPedido([
            ['id_producto' => $this->bandeja->id_producto, 'cantidad' => 1],
            ['id_producto' => $this->limonada->id_producto, 'cantidad' => 1],
        ]);
        [$lineaBandeja, $lineaLimonada] = $pedido['detalles'];

        $this->putJson("/api/pedidos/{$pedido['id_pedido']}/productos/{$lineaBandeja['id_detalle']}", [
            'cantidad' => 3,
            'observacion' => 'término medio',
        ])->assertOk()->assertJsonPath('data.detalles.0.cantidad', 3);

        $this->deleteJson("/api/pedidos/{$pedido['id_pedido']}/productos/{$lineaLimonada['id_detalle']}")
            ->assertOk()
            ->assertJsonCount(1, 'data.detalles')
            ->assertJsonPath('data.total', 96000);

        $this->putJson("/api/pedidos/{$pedido['id_pedido']}", ['observaciones' => 'Cliente alérgico al maní'])
            ->assertOk()
            ->assertJsonPath('data.observaciones', 'Cliente alérgico al maní');
    }

    public function test_no_modifica_lineas_de_otro_pedido(): void
    {
        $uno = $this->crearPedido([['id_producto' => $this->bandeja->id_producto, 'cantidad' => 1]]);
        $otro = $this->crearPedido();

        $this->putJson("/api/pedidos/{$otro['id_pedido']}/productos/{$uno['detalles'][0]['id_detalle']}", ['cantidad' => 5])
            ->assertNotFound();
    }

    public function test_no_se_modifica_despues_de_enviar_a_cocina(): void
    {
        $pedido = $this->crearPedido([['id_producto' => $this->bandeja->id_producto, 'cantidad' => 1]]);
        $this->patchJson("/api/pedidos/{$pedido['id_pedido']}/enviar-cocina")->assertOk();

        $this->postJson("/api/pedidos/{$pedido['id_pedido']}/productos", ['id_producto' => $this->limonada->id_producto, 'cantidad' => 1])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'pedido_no_editable');
    }

    // --- Permisos ------------------------------------------------------------

    public function test_permisos_para_tomar_pedidos(): void
    {
        $this->comoUsuario($this->crearUsuario(Rol::COCINERO))
            ->postJson('/api/pedidos', ['id_mesa' => $this->mesa->id_mesa])
            ->assertForbidden();

        $this->comoUsuario($this->crearUsuario(Rol::CAJERO))
            ->postJson('/api/pedidos', ['id_mesa' => $this->mesa->id_mesa])
            ->assertForbidden();

        // El cajero sí consulta los pedidos (para facturar).
        $this->getJson('/api/pedidos')->assertOk();
    }
}
