<?php

namespace Tests\Feature\Facturacion;

use App\Enums\EstadoPedido;
use App\Enums\TipoEvento;
use App\Models\DetallePedido;
use App\Models\Factura;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Módulo 6: RF28 Generar factura, RF29 Impuestos, RF30 Descuentos, RF31 Pagos y RF32 Comprobante. */
class FacturacionTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $cajero;

    private Usuario $mesero;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();

        $this->cajero = $this->crearUsuario(Rol::CAJERO);
        $this->mesero = $this->crearUsuario(Rol::MESERO);
        $this->producto = Producto::factory()->create(['nombre' => 'Ajiaco', 'precio' => 24000]);
    }

    /** Pedido entregado de 2 × 24.000 = 48.000 (impuesto del 8 % incluido: 3.555,56). */
    private function pedidoEntregado(EstadoPedido $estado = EstadoPedido::ENTREGADO): Pedido
    {
        $pedido = Pedido::factory()->enEstado($estado)->create(['id_usuario' => $this->mesero->id_usuario]);
        DetallePedido::factory()->create([
            'id_pedido' => $pedido->id_pedido,
            'id_producto' => $this->producto->id_producto,
            'cantidad' => 2,
            'precio_unitario' => 24000,
        ]);

        return $pedido;
    }

    private function generar(Pedido $pedido, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->comoUsuario($this->cajero)->postJson('/api/facturas', ['id_pedido' => $pedido->id_pedido, ...$extra]);
    }

    private function facturaPendiente(array $extra = []): Factura
    {
        $pedido = $this->pedidoEntregado();
        $id = $this->generar($pedido, $extra)->assertCreated()->json('data.id_factura');

        return Factura::findOrFail($id);
    }

    // --- RF28 Generar factura -------------------------------------------------

    public function test_genera_la_factura_de_un_pedido_entregado(): void
    {
        $pedido = $this->pedidoEntregado();

        $this->generar($pedido, ['cliente_nombre' => 'Ana Pérez', 'cliente_documento' => '1020304050'])
            ->assertCreated()
            ->assertJsonPath('data.numero', 'FAC-000001')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.cliente_nombre', 'Ana Pérez')
            ->assertJsonPath('data.subtotal', 48000)
            ->assertJsonPath('data.total', 48000)
            ->assertJsonPath('data.saldo', 48000)
            ->assertJsonPath('data.puede_pagar', true)
            ->assertJsonCount(1, 'data.detalles')
            ->assertJsonPath('data.detalles.0.descripcion', 'Ajiaco')
            ->assertJsonPath('data.detalles.0.subtotal', 48000);

        $this->assertDatabaseHas('facturas', ['id_pedido' => $pedido->id_pedido, 'id_usuario' => $this->cajero->id_usuario]);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::FACTURA_GENERADA->value]);
        // El pedido sigue "Entregado" hasta que se pague.
        $this->assertSame(EstadoPedido::ENTREGADO, $pedido->fresh()->estado);
    }

    public function test_cliente_por_defecto_es_consumidor_final(): void
    {
        $this->generar($this->pedidoEntregado())
            ->assertCreated()
            ->assertJsonPath('data.cliente_nombre', 'Consumidor final')
            ->assertJsonPath('data.cliente_documento', null);
    }

    public function test_la_factura_conserva_el_precio_aunque_cambie_el_menu(): void
    {
        $factura = $this->facturaPendiente();
        $this->producto->update(['precio' => 99000, 'nombre' => 'Otro nombre']);

        $this->comoUsuario($this->cajero)
            ->getJson("/api/facturas/{$factura->id_factura}")
            ->assertOk()
            ->assertJsonPath('total', 48000)
            ->assertJsonPath('detalles.0.descripcion', 'Ajiaco');
    }

    public function test_solo_se_facturan_pedidos_entregados(): void
    {
        foreach ([EstadoPedido::PENDIENTE, EstadoPedido::EN_PREPARACION, EstadoPedido::LISTO, EstadoPedido::CANCELADO] as $estado) {
            $this->generar($this->pedidoEntregado($estado))
                ->assertStatus(409)
                ->assertJsonPath('codigo', 'pedido_no_facturable');
        }

        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_no_factura_dos_veces_el_mismo_pedido(): void
    {
        $pedido = $this->pedidoEntregado();
        $this->generar($pedido)->assertCreated();

        $this->generar($pedido)->assertStatus(409)->assertJsonPath('codigo', 'pedido_ya_facturado');
        $this->assertDatabaseCount('facturas', 1);
    }

    public function test_no_factura_un_pedido_sin_productos(): void
    {
        $pedido = Pedido::factory()->enEstado(EstadoPedido::ENTREGADO)->create();

        $this->generar($pedido)->assertStatus(409);
    }

    public function test_valida_los_datos_de_la_factura(): void
    {
        $this->comoUsuario($this->cajero)
            ->postJson('/api/facturas', ['id_pedido' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_pedido');

        $this->comoUsuario($this->cajero)
            ->postJson('/api/facturas', ['id_pedido' => $this->pedidoEntregado()->id_pedido, 'descuento_tipo' => 'regalo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('descuento_tipo');
    }

    public function test_el_consecutivo_aumenta_con_cada_factura(): void
    {
        $this->generar($this->pedidoEntregado())->assertJsonPath('data.numero', 'FAC-000001');
        $this->generar($this->pedidoEntregado())->assertJsonPath('data.numero', 'FAC-000002');
    }

    public function test_pedidos_por_cobrar(): void
    {
        $sinFactura = $this->pedidoEntregado();
        $this->generar($this->pedidoEntregado())->assertCreated();
        $this->pedidoEntregado(EstadoPedido::LISTO);

        $this->comoUsuario($this->cajero)
            ->getJson('/api/facturas/por-cobrar')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id_pedido', $sinFactura->id_pedido);
    }

    public function test_el_pedido_muestra_su_factura_vigente(): void
    {
        $pedido = $this->pedidoEntregado();
        $this->generar($pedido)->assertCreated();

        $this->comoUsuario($this->cajero)
            ->getJson("/api/pedidos/{$pedido->id_pedido}")
            ->assertOk()
            ->assertJsonPath('factura.numero', 'FAC-000001')
            ->assertJsonPath('factura.estado', 'pendiente');
    }

    // --- RF29 Impuestos -------------------------------------------------------

    public function test_discrimina_el_impuesto_incluido_en_el_precio(): void
    {
        $this->generar($this->pedidoEntregado())
            ->assertCreated()
            ->assertJsonPath('data.impuesto_nombre', 'Impuesto al consumo')
            ->assertJsonPath('data.impuesto_porcentaje', 8)
            ->assertJsonPath('data.impuesto_incluido', true)
            ->assertJsonPath('data.impuesto', 3555.56)
            ->assertJsonPath('data.base_gravable', 44444.44)
            ->assertJsonPath('data.total', 48000);
    }

    public function test_suma_el_impuesto_cuando_no_esta_incluido(): void
    {
        config(['siar.facturacion.impuesto.incluido_en_precio' => false, 'siar.facturacion.impuesto.porcentaje' => 19]);

        $this->generar($this->pedidoEntregado())
            ->assertCreated()
            ->assertJsonPath('data.impuesto_incluido', false)
            ->assertJsonPath('data.base_gravable', 48000)
            ->assertJsonPath('data.impuesto', 9120)
            ->assertJsonPath('data.total', 57120);
    }

    public function test_incluye_la_propina_y_los_descuentos_de_promociones_del_pedido(): void
    {
        $pedido = $this->pedidoEntregado();
        $pedido->forceFill(['propina' => 4000])->save();
        DetallePedido::where('id_pedido', $pedido->id_pedido)->update(['descuento' => 2000]);

        $this->generar($pedido)
            ->assertCreated()
            ->assertJsonPath('data.descuento_promociones', 2000)
            ->assertJsonPath('data.propina', 4000)
            ->assertJsonPath('data.total', 50000)
            ->assertJsonPath('data.detalles.0.subtotal', 46000);
    }

    // --- RF30 Descuentos ------------------------------------------------------

    public function test_aplica_un_descuento_por_porcentaje_al_generar(): void
    {
        $this->generar($this->pedidoEntregado(), [
            'descuento_tipo' => 'porcentaje',
            'descuento_valor' => 10,
            'motivo_descuento' => 'Cliente frecuente',
        ])
            ->assertCreated()
            ->assertJsonPath('data.descuento', 4800)
            ->assertJsonPath('data.total', 43200)
            ->assertJsonPath('data.motivo_descuento', 'Cliente frecuente');
    }

    public function test_el_descuento_exige_motivo(): void
    {
        $this->generar($this->pedidoEntregado(), ['descuento_tipo' => 'valor', 'descuento_valor' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo_descuento');

        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_rechaza_descuentos_que_superan_la_cuenta(): void
    {
        $pedido = $this->pedidoEntregado();

        $this->generar($pedido, ['descuento_tipo' => 'valor', 'descuento_valor' => 48001, 'motivo_descuento' => 'Error'])
            ->assertStatus(422)->assertJsonValidationErrors('descuento_valor');
        $this->generar($pedido, ['descuento_tipo' => 'porcentaje', 'descuento_valor' => 120, 'motivo_descuento' => 'Error'])
            ->assertStatus(422)->assertJsonValidationErrors('descuento_valor');
        $this->generar($pedido, ['descuento_tipo' => 'porcentaje', 'motivo_descuento' => 'Falta el valor'])
            ->assertStatus(422)->assertJsonValidationErrors('descuento_valor');
    }

    public function test_aplica_cambia_y_quita_el_descuento_de_una_factura_pendiente(): void
    {
        $factura = $this->facturaPendiente();
        $url = "/api/facturas/{$factura->id_factura}/descuento";
        $cajero = $this->comoUsuario($this->cajero);

        $cajero->patchJson($url, ['descuento_tipo' => 'valor', 'descuento_valor' => 8000, 'motivo_descuento' => 'Demora en el servicio'])
            ->assertOk()
            ->assertJsonPath('data.descuento', 8000)
            ->assertJsonPath('data.total', 40000);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::FACTURA_DESCUENTO_APLICADO->value]);

        $cajero->patchJson($url, ['descuento_tipo' => 'porcentaje', 'descuento_valor' => 25, 'motivo_descuento' => 'Gerencia'])
            ->assertOk()->assertJsonPath('data.total', 36000);

        $cajero->patchJson($url, ['descuento_tipo' => 'ninguno'])
            ->assertOk()
            ->assertJsonPath('data.descuento', 0)
            ->assertJsonPath('data.motivo_descuento', null)
            ->assertJsonPath('data.total', 48000);
    }

    public function test_no_cambia_el_descuento_si_ya_hay_pagos(): void
    {
        $factura = $this->facturaPendiente();
        $cajero = $this->comoUsuario($this->cajero);
        $cajero->postJson("/api/facturas/{$factura->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 10000])->assertCreated();

        $cajero->patchJson("/api/facturas/{$factura->id_factura}/descuento", [
            'descuento_tipo' => 'valor', 'descuento_valor' => 1000, 'motivo_descuento' => 'Tarde',
        ])->assertStatus(409)->assertJsonPath('codigo', 'factura_con_pagos');
    }

    public function test_previsualiza_la_cuenta_sin_guardar(): void
    {
        $pedido = $this->pedidoEntregado();

        $this->comoUsuario($this->cajero)
            ->postJson('/api/facturas/previsualizar', ['id_pedido' => $pedido->id_pedido, 'descuento_tipo' => 'porcentaje', 'descuento_valor' => 50])
            ->assertOk()
            ->assertJsonPath('data.descuento', 24000)
            ->assertJsonPath('data.total', 24000);

        $this->assertDatabaseCount('facturas', 0);
    }

    // --- RF31 Pagos -----------------------------------------------------------

    public function test_el_pago_total_en_efectivo_paga_la_factura_y_factura_el_pedido(): void
    {
        $factura = $this->facturaPendiente();

        $this->comoUsuario($this->cajero)
            ->postJson("/api/facturas/{$factura->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 48000, 'monto_recibido' => 50000])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pagada')
            ->assertJsonPath('data.saldo', 0)
            ->assertJsonPath('data.total_pagado', 48000)
            ->assertJsonPath('data.puede_pagar', false)
            ->assertJsonPath('data.pagos.0.cambio', 2000)
            ->assertJsonPath('data.pagos.0.monto_recibido', 50000);

        $pedido = $factura->pedido->fresh();
        $this->assertSame(EstadoPedido::FACTURADO, $pedido->estado);
        $this->assertSame('pagado', $pedido->estado_pago);
        $this->assertNotNull($factura->fresh()->fecha_pago);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::FACTURA_PAGO_REGISTRADO->value]);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::FACTURA_PAGADA->value]);
    }

    public function test_pago_dividido_en_efectivo_y_tarjeta(): void
    {
        $factura = $this->facturaPendiente();
        $url = "/api/facturas/{$factura->id_factura}/pagos";
        $cajero = $this->comoUsuario($this->cajero);

        $cajero->postJson($url, ['metodo' => 'efectivo', 'monto' => 20000])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.saldo', 28000)
            ->assertJsonPath('data.puede_aplicar_descuento', false);
        $this->assertSame(EstadoPedido::ENTREGADO, $factura->pedido->fresh()->estado);

        $cajero->postJson($url, ['metodo' => 'tarjeta', 'monto' => 28000, 'referencia' => 'AP-7788'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pagada')
            ->assertJsonCount(2, 'data.pagos')
            ->assertJsonPath('data.pagos.1.referencia', 'AP-7788');

        $this->assertSame(EstadoPedido::FACTURADO, $factura->pedido->fresh()->estado);
    }

    public function test_valida_el_pago(): void
    {
        $factura = $this->facturaPendiente();
        $url = "/api/facturas/{$factura->id_factura}/pagos";
        $cajero = $this->comoUsuario($this->cajero);

        // Más que el saldo.
        $cajero->postJson($url, ['metodo' => 'efectivo', 'monto' => 48001])
            ->assertStatus(422)->assertJsonValidationErrors('monto');
        // Tarjeta y transferencia llevan referencia.
        $cajero->postJson($url, ['metodo' => 'tarjeta', 'monto' => 10000])
            ->assertStatus(422)->assertJsonValidationErrors('referencia');
        $cajero->postJson($url, ['metodo' => 'transferencia', 'monto' => 10000])
            ->assertStatus(422)->assertJsonValidationErrors('referencia');
        // Efectivo recibido insuficiente.
        $cajero->postJson($url, ['metodo' => 'efectivo', 'monto' => 10000, 'monto_recibido' => 9000])
            ->assertStatus(422)->assertJsonValidationErrors('monto_recibido');
        // Monto y método inválidos.
        $cajero->postJson($url, ['metodo' => 'efectivo', 'monto' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('monto');
        $cajero->postJson($url, ['metodo' => 'cheque', 'monto' => 1000])
            ->assertStatus(422)->assertJsonValidationErrors('metodo');

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_no_acepta_pagos_en_una_factura_pagada_o_anulada(): void
    {
        $pagada = $this->facturaPendiente();
        $cajero = $this->comoUsuario($this->cajero);
        $cajero->postJson("/api/facturas/{$pagada->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 48000])->assertCreated();
        $cajero->postJson("/api/facturas/{$pagada->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 1000])
            ->assertStatus(409)->assertJsonPath('codigo', 'factura_no_pendiente');

        $anulada = $this->facturaPendiente();
        $cajero->patchJson("/api/facturas/{$anulada->id_factura}/anular", ['motivo' => 'Error de digitación'])->assertOk();
        $cajero->postJson("/api/facturas/{$anulada->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 1000])
            ->assertStatus(409);
    }

    public function test_tras_el_pago_la_mesa_se_puede_liberar(): void
    {
        $mesa = Mesa::factory()->create();
        $mesa->ocupar();
        $pedido = $this->pedidoEntregado();
        $pedido->forceFill(['id_mesa' => $mesa->id_mesa])->save();

        $cajero = $this->comoUsuario($this->cajero);
        // Con el pedido sin pagar, la mesa sigue con una cuenta abierta (RF16).
        $cajero->patchJson("/api/mesas/{$mesa->id_mesa}/liberar")->assertStatus(409);

        $id = $this->generar($pedido)->assertCreated()->json('data.id_factura');
        $cajero->postJson("/api/facturas/{$id}/pagos", ['metodo' => 'efectivo', 'monto' => 48000])->assertCreated();

        $cajero->patchJson("/api/mesas/{$mesa->id_mesa}/liberar")->assertOk()->assertJsonPath('data.estado', 'libre');
    }

    // --- Anular ---------------------------------------------------------------

    public function test_anula_una_factura_pendiente_y_permite_facturar_de_nuevo(): void
    {
        $factura = $this->facturaPendiente();
        $cajero = $this->comoUsuario($this->cajero);

        $cajero->patchJson("/api/facturas/{$factura->id_factura}/anular", ['motivo' => 'Cliente pidió datos fiscales'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'anulada')
            ->assertJsonPath('data.motivo_anulacion', 'Cliente pidió datos fiscales')
            ->assertJsonPath('data.puede_pagar', false);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::FACTURA_ANULADA->value]);

        // El pedido sigue entregado: se puede generar otra factura.
        $this->assertSame(EstadoPedido::ENTREGADO, $factura->pedido->fresh()->estado);
        $this->generar($factura->pedido)->assertCreated()->assertJsonPath('data.numero', 'FAC-000002');
    }

    public function test_no_anula_una_factura_con_pagos_y_exige_motivo(): void
    {
        $factura = $this->facturaPendiente();
        $url = "/api/facturas/{$factura->id_factura}/anular";
        $cajero = $this->comoUsuario($this->cajero);

        $cajero->patchJson($url, [])->assertStatus(422)->assertJsonValidationErrors('motivo');

        $cajero->postJson("/api/facturas/{$factura->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 1000])->assertCreated();
        $cajero->patchJson($url, ['motivo' => 'Ya no la quiere'])->assertStatus(409)->assertJsonPath('codigo', 'factura_con_pagos');
    }

    // --- RF32 Comprobante -----------------------------------------------------

    public function test_emite_el_comprobante_de_una_factura_pagada(): void
    {
        $factura = $this->facturaPendiente(['cliente_nombre' => 'Ana Pérez']);
        $cajero = $this->comoUsuario($this->cajero);
        $cajero->postJson("/api/facturas/{$factura->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 48000, 'monto_recibido' => 50000])->assertCreated();

        $cajero->postJson("/api/facturas/{$factura->id_factura}/comprobante")
            ->assertOk()
            ->assertJsonPath('titulo', 'Comprobante de pago')
            ->assertJsonPath('emisor.nombre', config('siar.facturacion.emisor.nombre'))
            ->assertJsonPath('factura.numero', 'FAC-000001')
            ->assertJsonPath('factura.cliente_nombre', 'Ana Pérez')
            ->assertJsonPath('cambio_total', 2000)
            ->assertJsonCount(1, 'factura.detalles')
            ->assertJsonCount(1, 'factura.pagos');

        $this->assertNotNull($factura->fresh()->comprobante_emitido_en);
        $this->assertDatabaseHas('bitacora_auditoria', ['tipo_evento' => TipoEvento::COMPROBANTE_EMITIDO->value]);

        // Reimprimir no cambia la fecha de la primera emisión.
        $primera = $factura->fresh()->comprobante_emitido_en;
        $cajero->postJson("/api/facturas/{$factura->id_factura}/comprobante")->assertOk();
        $this->assertEquals($primera, $factura->fresh()->comprobante_emitido_en);
    }

    public function test_la_factura_pendiente_se_imprime_pero_no_marca_comprobante(): void
    {
        $factura = $this->facturaPendiente();

        $this->comoUsuario($this->cajero)
            ->postJson("/api/facturas/{$factura->id_factura}/comprobante")
            ->assertOk()
            ->assertJsonPath('titulo', 'Factura pendiente de pago');

        $this->assertNull($factura->fresh()->comprobante_emitido_en);
    }

    public function test_una_factura_anulada_no_tiene_comprobante(): void
    {
        $factura = $this->facturaPendiente();
        $cajero = $this->comoUsuario($this->cajero);
        $cajero->patchJson("/api/facturas/{$factura->id_factura}/anular", ['motivo' => 'Duplicada'])->assertOk();

        $cajero->postJson("/api/facturas/{$factura->id_factura}/comprobante")->assertStatus(409);
    }

    // --- Consulta y permisos --------------------------------------------------

    public function test_lista_y_filtra_las_facturas(): void
    {
        $pagada = $this->facturaPendiente(['cliente_nombre' => 'Carlos Ruiz']);
        $this->facturaPendiente(['cliente_nombre' => 'Laura Gómez']);
        $cajero = $this->comoUsuario($this->cajero);
        $cajero->postJson("/api/facturas/{$pagada->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 48000])->assertCreated();

        $cajero->getJson('/api/facturas')->assertOk()->assertJsonCount(2, 'data');
        $cajero->getJson('/api/facturas?estado=pagada')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.numero', 'FAC-000001');
        $cajero->getJson('/api/facturas?buscar=Laura')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cliente_nombre', 'Laura Gómez');
        $cajero->getJson('/api/facturas?buscar=FAC-000002')->assertOk()->assertJsonCount(1, 'data');
        $cajero->getJson('/api/facturas?desde='.now()->addDay()->format('Y-m-d'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_solo_quien_puede_facturar_accede(): void
    {
        $factura = $this->facturaPendiente();

        // Sin sesión: se quita el token que dejó facturaPendiente().
        $this->flushHeaders();
        $this->refrescarAutenticacion();
        $this->getJson('/api/facturas')->assertUnauthorized();

        foreach ([Rol::MESERO, Rol::COCINERO] as $rol) {
            $usuario = $this->crearUsuario($rol);
            $this->comoUsuario($usuario)->getJson('/api/facturas')->assertForbidden();
            $this->comoUsuario($usuario)->getJson("/api/facturas/{$factura->id_factura}")->assertForbidden();
            $this->comoUsuario($usuario)->postJson('/api/facturas', ['id_pedido' => $factura->id_pedido])->assertForbidden();
            $this->comoUsuario($usuario)->postJson("/api/facturas/{$factura->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 1000])->assertForbidden();
        }

        $this->comoUsuario($this->crearUsuario(Rol::ADMINISTRADOR))->getJson('/api/facturas')->assertOk();
    }

    public function test_un_usuario_con_pagos_se_inactiva_en_lugar_de_borrarse(): void
    {
        $factura = $this->facturaPendiente();
        $this->comoUsuario($this->cajero)->postJson("/api/facturas/{$factura->id_factura}/pagos", ['metodo' => 'efectivo', 'monto' => 1000])->assertCreated();

        $this->comoUsuario($this->crearUsuario(Rol::ADMINISTRADOR))
            ->deleteJson("/api/usuarios/{$this->cajero->id_usuario}")
            ->assertOk();

        $this->assertDatabaseHas('usuarios', ['id_usuario' => $this->cajero->id_usuario]);
    }
}
