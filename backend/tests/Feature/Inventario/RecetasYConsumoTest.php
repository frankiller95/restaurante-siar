<?php

namespace Tests\Feature\Inventario;

use App\Enums\EstadoPedido;
use App\Enums\TipoEvento;
use App\Models\DetallePedido;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF38 Recetas, RF39 consumo por pedidos y RF40 disponibilidad automática. */
class RecetasYConsumoTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    private Usuario $mesero;

    private Producto $arrozConPollo;

    private Insumo $arroz;

    private Insumo $pollo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();

        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
        $this->mesero = $this->crearUsuario(Rol::MESERO);
        $this->arrozConPollo = Producto::factory()->create(['nombre' => 'Arroz con pollo', 'precio' => 22000]);
        $this->arroz = Insumo::factory()->conStock(10, 2)->create(['nombre' => 'Arroz', 'costo_unitario' => 4000]);
        $this->pollo = Insumo::factory()->conStock(5, 1)->create(['nombre' => 'Pollo', 'costo_unitario' => 12000]);
    }

    private function guardarReceta(): void
    {
        $this->comoUsuario($this->admin)
            ->putJson("/api/productos/{$this->arrozConPollo->id_producto}/receta", [
                'insumos' => [
                    ['id_insumo' => $this->arroz->id_insumo, 'cantidad_por_porcion' => 0.15],
                    ['id_insumo' => $this->pollo->id_insumo, 'cantidad_por_porcion' => 0.25],
                ],
            ])
            ->assertOk();
    }

    /** Pedido pendiente con N porciones del producto, tomado por el mesero. */
    private function pedidoDe(int $porciones): Pedido
    {
        $pedido = Pedido::factory()->create(['id_mesa' => Mesa::factory()->create()->id_mesa, 'id_usuario' => $this->mesero->id_usuario]);
        DetallePedido::factory()->create([
            'id_pedido' => $pedido->id_pedido,
            'id_producto' => $this->arrozConPollo->id_producto,
            'cantidad' => $porciones,
            'precio_unitario' => 22000,
        ]);

        return $pedido;
    }

    // --- RF38 Recetas ----------------------------------------------------------

    public function test_define_la_receta_con_su_costo_por_porcion(): void
    {
        $this->guardarReceta();

        $this->getJson("/api/productos/{$this->arrozConPollo->id_producto}/receta")
            ->assertOk()
            ->assertJsonCount(2, 'insumos')
            ->assertJsonPath('insumos.0.nombre', 'Arroz')
            ->assertJsonPath('insumos.0.unidad', 'kg')
            ->assertJsonPath('costo_por_porcion', 3600)
            ->assertJsonPath('producto.alcanza_para_una_porcion', true);

        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::RECETA_ACTUALIZADA->value]);
    }

    public function test_valida_la_receta(): void
    {
        $url = "/api/productos/{$this->arrozConPollo->id_producto}/receta";
        $this->comoUsuario($this->admin);

        $this->putJson($url, ['insumos' => [
            ['id_insumo' => $this->arroz->id_insumo, 'cantidad_por_porcion' => 0.1],
            ['id_insumo' => $this->arroz->id_insumo, 'cantidad_por_porcion' => 0.2],
        ]])->assertStatus(422);

        $this->putJson($url, ['insumos' => [['id_insumo' => $this->arroz->id_insumo, 'cantidad_por_porcion' => 0]]])
            ->assertStatus(422);

        $this->arroz->darDeBaja();
        $this->putJson($url, ['insumos' => [['id_insumo' => $this->arroz->id_insumo, 'cantidad_por_porcion' => 0.1]]])
            ->assertStatus(422)->assertJsonValidationErrors('insumos');
    }

    // --- RF39 Consumo por pedidos ----------------------------------------------

    public function test_enviar_un_pedido_a_cocina_descuenta_los_insumos(): void
    {
        $this->guardarReceta();
        $pedido = $this->pedidoDe(4);

        $this->comoUsuario($this->mesero)->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")->assertOk();

        $this->assertEquals(9.4, (float) $this->arroz->fresh()->stock_actual);
        $this->assertEquals(4.0, (float) $this->pollo->fresh()->stock_actual);

        $this->assertDatabaseHas('movimientos_inventario', [
            'id_insumo' => $this->pollo->id_insumo,
            'tipo' => 'consumo_pedido',
            'id_pedido' => $pedido->id_pedido,
            'id_usuario' => $this->mesero->id_usuario,
        ]);
    }

    public function test_cancelar_antes_de_preparar_reintegra_los_insumos(): void
    {
        $this->guardarReceta();
        $pedido = $this->pedidoDe(2);

        $this->comoUsuario($this->mesero);
        $this->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")->assertOk();
        $this->patchJson("/api/pedidos/{$pedido->id_pedido}/cancelar")->assertOk();

        $this->assertEquals(10.0, (float) $this->arroz->fresh()->stock_actual);
        $this->assertEquals(5.0, (float) $this->pollo->fresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['tipo' => 'reintegro_pedido', 'id_pedido' => $pedido->id_pedido]);
        $this->assertDatabaseMissing('movimientos_inventario', ['tipo' => 'merma', 'id_pedido' => $pedido->id_pedido]);
    }

    public function test_cancelar_en_preparacion_lo_registra_como_merma(): void
    {
        $this->guardarReceta();
        $pedido = $this->pedidoDe(2);

        $this->comoUsuario($this->mesero)->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")->assertOk();
        $pedido->refresh()->iniciarPreparacion();
        $this->patchJson("/api/pedidos/{$pedido->id_pedido}/cancelar", ['motivo' => 'Se fue la luz'])->assertOk();

        // El stock no vuelve (ya se cocinó), pero queda como merma.
        $this->assertEquals(9.7, (float) $this->arroz->fresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['tipo' => 'merma', 'id_pedido' => $pedido->id_pedido, 'id_insumo' => $this->arroz->id_insumo]);
    }

    public function test_cancelar_sin_haber_enviado_a_cocina_no_mueve_inventario(): void
    {
        $this->guardarReceta();
        $pedido = $this->pedidoDe(2);

        $this->comoUsuario($this->mesero)->patchJson("/api/pedidos/{$pedido->id_pedido}/cancelar")->assertOk();

        $this->assertDatabaseMissing('movimientos_inventario', ['id_pedido' => $pedido->id_pedido]);
    }

    public function test_productos_sin_receta_no_descuentan(): void
    {
        $pedido = $this->pedidoDe(3);

        $this->comoUsuario($this->mesero)->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")->assertOk();

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    // --- RF40 Disponibilidad automática ----------------------------------------

    public function test_se_agota_cuando_no_alcanza_y_se_repone_con_una_entrada(): void
    {
        $this->guardarReceta();

        // Quedan 0,2 kg de pollo: no alcanza para una porción (0,25).
        $this->comoUsuario($this->admin)
            ->postJson("/api/insumos/{$this->pollo->id_insumo}/mermas", ['cantidad' => 4.8, 'motivo' => 'Cadena de frío rota'])
            ->assertCreated();

        $producto = $this->arrozConPollo->fresh();
        $this->assertFalse($producto->disponible);
        $this->assertTrue($producto->agotado_por_inventario);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::PRODUCTO_AGOTADO_INVENTARIO->value]);

        // Llega pollo: el producto vuelve solo.
        $this->postJson("/api/insumos/{$this->pollo->id_insumo}/entradas", ['cantidad' => 3])->assertCreated();

        $producto->refresh();
        $this->assertTrue($producto->disponible);
        $this->assertFalse($producto->agotado_por_inventario);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::PRODUCTO_REPUESTO_INVENTARIO->value]);
    }

    public function test_el_consumo_por_pedido_tambien_agota_el_producto(): void
    {
        $this->guardarReceta();
        $pedido = $this->pedidoDe(19); // 19 × 0,25 = 4,75 kg de pollo → quedan 0,25 (alcanza justo para 1)

        $this->comoUsuario($this->mesero)->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")->assertOk();
        $this->assertTrue($this->arrozConPollo->fresh()->disponible);

        $otro = $this->pedidoDe(1);
        $this->patchJson("/api/pedidos/{$otro->id_pedido}/enviar-cocina")->assertOk();
        $this->assertFalse($this->arrozConPollo->fresh()->disponible);

        // Al cancelar el último antes de prepararlo, vuelve el pollo y el producto.
        $this->patchJson("/api/pedidos/{$otro->id_pedido}/cancelar")->assertOk();
        $this->assertTrue($this->arrozConPollo->fresh()->disponible);
    }

    public function test_no_reactiva_un_producto_que_el_cocinero_agoto_a_mano(): void
    {
        $this->guardarReceta();

        // El cocinero lo marca agotado a mano (CU05).
        $this->comoUsuario($this->crearUsuario(Rol::COCINERO))
            ->patchJson("/api/productos/{$this->arrozConPollo->id_producto}/disponibilidad", ['disponible' => false])
            ->assertOk();

        // Una entrada de insumos no lo reactiva.
        $this->comoUsuario($this->admin)
            ->postJson("/api/insumos/{$this->arroz->id_insumo}/entradas", ['cantidad' => 5])
            ->assertCreated();

        $this->assertFalse($this->arrozConPollo->fresh()->disponible);
    }

    public function test_una_receta_nueva_que_no_alcanza_agota_el_producto(): void
    {
        $this->comoUsuario($this->admin)
            ->putJson("/api/productos/{$this->arrozConPollo->id_producto}/receta", [
                'insumos' => [['id_insumo' => $this->pollo->id_insumo, 'cantidad_por_porcion' => 6]],
            ])
            ->assertOk()
            ->assertJsonPath('producto.disponible', false)
            ->assertJsonPath('producto.agotado_por_inventario', true);

        // Quitar la receta lo vuelve a dejar disponible.
        $this->putJson("/api/productos/{$this->arrozConPollo->id_producto}/receta", ['insumos' => []])
            ->assertOk()
            ->assertJsonPath('producto.disponible', true);
    }

    public function test_el_envio_y_el_descuento_quedan_juntos(): void
    {
        // Envío a cocina y descuento ocurren en la misma transacción: un consumo por insumo.
        $this->guardarReceta();
        $pedido = $this->pedidoDe(1);

        $this->comoUsuario($this->mesero)->patchJson("/api/pedidos/{$pedido->id_pedido}/enviar-cocina")->assertOk();

        $this->assertNotNull($pedido->fresh()->fecha_envio_cocina);
        $this->assertSame(2, MovimientoInventario::where('id_pedido', $pedido->id_pedido)->count());
        $this->assertSame(EstadoPedido::PENDIENTE, $pedido->fresh()->estado);
    }
}
