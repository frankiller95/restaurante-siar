<?php

namespace Tests\Feature\Reportes;

use App\Enums\EstadoPedido;
use App\Enums\TipoMovimiento;
use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Insumo;
use App\Models\Mesa;
use App\Models\MovimientoInventario;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Módulo 7: RF33 Ventas, RF34 Productos más vendidos, RF35 Consumo de inventario,
 * RF36 Ocupación de mesas y RF37 Indicadores financieros.
 *
 * Las fechas de los datos son hora del restaurante (Bogotá, UTC-5); en la base
 * se guardan en UTC.
 */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private const ZONA = 'America/Bogota';

    /** Semana de prueba: lunes 2 al domingo 8 de noviembre de 2026. */
    private const SEMANA = 'desde=2026-11-02&hasta=2026-11-08';

    private Usuario $admin;

    private Usuario $cajero;

    private int $consecutivo = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarRolesYPermisos();
        // "Ahora" queda después de los periodos de prueba.
        $this->travelTo(CarbonImmutable::parse('2026-12-01 12:00:00', self::ZONA));

        $this->admin = $this->crearUsuario(Rol::ADMINISTRADOR);
        $this->cajero = $this->crearUsuario(Rol::CAJERO);
    }

    private function utc(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, self::ZONA)->utc();
    }

    /**
     * Factura con sus líneas y su pago. $lineas: [[descripcion, cantidad, precio], ...].
     * Opciones: estado (pagada/pendiente/anulada), propina, metodo, pedido, fecha_anulacion, abono.
     *
     * @param  list<array{0: string, 1: int, 2: float}>  $lineas
     * @param  array<string, mixed>  $op
     */
    private function factura(?string $pagoLocal, array $lineas, array $op = []): Factura
    {
        $estado = $op['estado'] ?? 'pagada';
        $propina = (float) ($op['propina'] ?? 0);
        $pedido = $op['pedido'] ?? Pedido::factory()->enEstado(EstadoPedido::FACTURADO)->create();

        $neto = array_sum(array_map(fn (array $l) => $l[1] * $l[2], $lineas));
        $impuesto = round($neto * 8 / 108, 2);
        $total = $neto + $propina;
        $fecha = $pagoLocal ? $this->utc($pagoLocal) : null;

        $factura = (new Factura)->forceFill([
            'numero' => 'FAC-'.str_pad((string) ++$this->consecutivo, 6, '0', STR_PAD_LEFT),
            'id_pedido' => $pedido->id_pedido,
            'id_usuario' => $this->cajero->id_usuario,
            'estado' => $estado,
            'cliente_nombre' => 'Consumidor final',
            'subtotal' => $neto,
            'descuento_promociones' => 0,
            'descuento_tipo' => 'ninguno',
            'descuento_valor' => 0,
            'descuento' => 0,
            'impuesto_nombre' => 'Impuesto al consumo',
            'impuesto_porcentaje' => 8,
            'impuesto_incluido' => true,
            'base_gravable' => $neto - $impuesto,
            'impuesto' => $impuesto,
            'propina' => $propina,
            'total' => $total,
            'fecha_emision' => $fecha ?? $this->utc('2026-11-01 08:00:00'),
            'fecha_pago' => $estado === 'pagada' ? $fecha : null,
            'fecha_anulacion' => isset($op['fecha_anulacion']) ? $this->utc($op['fecha_anulacion']) : null,
        ]);
        $factura->save();

        foreach ($lineas as [$descripcion, $cantidad, $precio]) {
            DetalleFactura::create([
                'id_factura' => $factura->id_factura,
                'id_producto' => null,
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento' => 0,
                'subtotal' => $cantidad * $precio,
            ]);
        }

        $abono = $estado === 'pagada' ? $total : ($op['abono'] ?? 0);
        if ($abono > 0) {
            Pago::create([
                'id_factura' => $factura->id_factura,
                'metodo' => $op['metodo'] ?? 'efectivo',
                'monto' => $abono,
                'cambio' => 0,
                'id_usuario' => $this->cajero->id_usuario,
                'fecha' => $fecha ?? $this->utc('2026-11-01 08:00:00'),
            ]);
        }

        return $factura;
    }

    private function reporte(Usuario $usuario, string $ruta): \Illuminate\Testing\TestResponse
    {
        return $this->comoUsuario($usuario)->getJson("/api/reportes/{$ruta}");
    }

    /** Facturas de la semana de prueba (ventas): 166.000 en 3 facturas + 3 que no cuentan. */
    private function datosDeVentas(): void
    {
        $this->factura('2026-11-02 12:00:00', [['Ajiaco', 2, 24000]], ['metodo' => 'efectivo']);
        // 21:00 en Bogotá = 02:00 UTC del día siguiente: sigue siendo del lunes 2.
        $this->factura('2026-11-02 21:00:00', [['Bandeja', 1, 30000]], ['metodo' => 'tarjeta']);
        $this->factura('2026-11-04 13:00:00', [['Ajiaco', 1, 24000], ['Bandeja', 2, 30000]], ['metodo' => 'transferencia', 'propina' => 4000]);

        // No son ventas del periodo:
        $this->factura(null, [['Ajiaco', 1, 24000]], ['estado' => 'pendiente']);
        $this->factura('2026-11-03 10:00:00', [['Ajiaco', 1, 24000]], ['estado' => 'anulada']);
        $this->factura('2026-11-09 10:00:00', [['Ajiaco', 1, 24000]]);
    }

    // --- Permisos --------------------------------------------------------------

    public function test_el_administrador_ve_todos_los_reportes(): void
    {
        foreach (['ventas', 'productos-mas-vendidos', 'consumo-inventario', 'ocupacion-mesas', 'indicadores-financieros'] as $reporte) {
            $this->reporte($this->admin, "{$reporte}?".self::SEMANA)->assertOk();
        }
    }

    public function test_el_cajero_solo_ve_inventario_y_ocupacion(): void
    {
        $this->reporte($this->cajero, 'consumo-inventario?'.self::SEMANA)->assertOk();
        $this->reporte($this->cajero, 'ocupacion-mesas?'.self::SEMANA)->assertOk();

        foreach (['ventas', 'productos-mas-vendidos', 'indicadores-financieros'] as $reporte) {
            $this->reporte($this->cajero, "{$reporte}?".self::SEMANA)->assertForbidden();
        }
    }

    public function test_mesero_y_cocinero_no_ven_reportes(): void
    {
        foreach ([Rol::MESERO, Rol::COCINERO] as $rol) {
            $usuario = $this->crearUsuario($rol);

            foreach (['ventas', 'productos-mas-vendidos', 'consumo-inventario', 'ocupacion-mesas', 'indicadores-financieros'] as $reporte) {
                $this->reporte($usuario, $reporte)->assertForbidden();
            }
        }
    }

    public function test_sin_sesion_no_hay_reportes(): void
    {
        $this->flushHeaders();
        $this->refrescarAutenticacion();

        $this->getJson('/api/reportes/ventas')->assertUnauthorized();
        $this->getJson('/api/reportes/ocupacion-mesas')->assertUnauthorized();
    }

    public function test_la_migracion_da_los_permisos_a_administrador_y_cajero_sin_duplicar(): void
    {
        DB::table('rol_permisos')->delete();

        $migracion = require database_path('migrations/2026_11_17_000001_agregar_permisos_de_reportes.php');
        $migracion->up();
        $migracion->up(); // se puede correr dos veces

        $this->assertTrue($this->admin->fresh()->tienePermiso('ver_reportes'));
        $this->assertTrue($this->admin->fresh()->tienePermiso('ver_reportes_operativos'));
        $this->assertTrue($this->cajero->fresh()->tienePermiso('ver_reportes_operativos'));
        $this->assertFalse($this->cajero->fresh()->tienePermiso('ver_reportes'));
        $this->assertFalse($this->crearUsuario(Rol::MESERO)->tienePermiso('ver_reportes_operativos'));
    }

    // --- Parámetros ------------------------------------------------------------

    public function test_valida_los_parametros(): void
    {
        $admin = $this->comoUsuario($this->admin);

        $admin->getJson('/api/reportes/ventas?desde=2026-11-10&hasta=2026-11-01')->assertStatus(422)->assertJsonValidationErrors('hasta');
        $admin->getJson('/api/reportes/ventas?desde=2025-01-01&hasta=2026-11-01')->assertStatus(422)->assertJsonValidationErrors('hasta');
        $admin->getJson('/api/reportes/ventas?desde=hoy')->assertStatus(422)->assertJsonValidationErrors('desde');
        $admin->getJson('/api/reportes/ventas?agrupar=anio')->assertStatus(422)->assertJsonValidationErrors('agrupar');
        $admin->getJson('/api/reportes/productos-mas-vendidos?limite=0')->assertStatus(422)->assertJsonValidationErrors('limite');
        $admin->getJson('/api/reportes/productos-mas-vendidos?ordenar_por=color')->assertStatus(422)->assertJsonValidationErrors('ordenar_por');
    }

    public function test_sin_fechas_usa_los_ultimos_30_dias(): void
    {
        $this->reporte($this->admin, 'ventas')
            ->assertOk()
            ->assertJsonPath('periodo.dias', 30)
            ->assertJsonPath('periodo.hasta', '2026-12-01')
            ->assertJsonCount(30, 'series');
    }

    // --- RF33 Ventas por periodo -----------------------------------------------

    public function test_ventas_por_dia_solo_cuenta_facturas_pagadas_del_periodo(): void
    {
        $this->datosDeVentas();

        $r = $this->reporte($this->admin, 'ventas?'.self::SEMANA.'&agrupar=dia')->assertOk();

        // 7 días, también los que no tuvieron ventas.
        $r->assertJsonCount(7, 'series')
            ->assertJsonPath('agrupacion', 'dia')
            ->assertJsonPath('totales.facturas', 3)
            ->assertJsonPath('totales.ventas', 166000)
            ->assertJsonPath('totales.propinas', 4000)
            ->assertJsonPath('totales.ticket_promedio', 55333.33);

        // Lunes 2: dos facturas (la de las 21:00 locales sigue siendo del lunes).
        $r->assertJsonPath('series.0.clave', '2026-11-02')
            ->assertJsonPath('series.0.facturas', 2)
            ->assertJsonPath('series.0.ventas', 78000)
            ->assertJsonPath('series.0.ticket_promedio', 39000)
            ->assertJsonPath('series.1.facturas', 0)
            ->assertJsonPath('series.1.ventas', 0)
            ->assertJsonPath('series.2.ventas', 88000);

        $r->assertJsonPath('mejor_periodo.ventas', 88000);
    }

    public function test_las_ventas_netas_no_incluyen_impuesto_ni_propina(): void
    {
        $this->factura('2026-11-04 13:00:00', [['Ajiaco', 3, 36000]], ['propina' => 10800]);

        // 108.000 con 8 % incluido: impuesto 8.000; propina 10.800 aparte.
        $this->reporte($this->admin, 'ventas?'.self::SEMANA)
            ->assertOk()
            ->assertJsonPath('totales.ventas', 118800)
            ->assertJsonPath('totales.impuestos', 8000)
            ->assertJsonPath('totales.propinas', 10800)
            ->assertJsonPath('totales.ventas_netas', 100000);
    }

    public function test_ventas_agrupadas_por_semana_y_por_mes(): void
    {
        $this->datosDeVentas();

        $this->reporte($this->admin, 'ventas?'.self::SEMANA.'&agrupar=semana')
            ->assertOk()
            ->assertJsonCount(1, 'series')
            ->assertJsonPath('series.0.clave', '2026-11-02')
            ->assertJsonPath('series.0.ventas', 166000);

        // Del 26 de octubre al 8 de noviembre: dos semanas completas.
        $this->reporte($this->admin, 'ventas?desde=2026-10-26&hasta=2026-11-08&agrupar=semana')
            ->assertOk()
            ->assertJsonCount(2, 'series')
            ->assertJsonPath('series.0.ventas', 0)
            ->assertJsonPath('series.1.ventas', 166000);

        $this->reporte($this->admin, 'ventas?desde=2026-10-01&hasta=2026-11-30&agrupar=mes')
            ->assertOk()
            ->assertJsonCount(2, 'series')
            ->assertJsonPath('series.0.clave', '2026-10-01')
            ->assertJsonPath('series.1.clave', '2026-11-01')
            ->assertJsonPath('series.1.ventas', 190000); // incluye la del 9 de noviembre
    }

    public function test_ventas_por_metodo_de_pago(): void
    {
        $this->datosDeVentas();

        $r = $this->reporte($this->admin, 'ventas?'.self::SEMANA)->assertOk();

        $r->assertJsonCount(3, 'por_metodo_pago')
            ->assertJsonPath('por_metodo_pago.0.metodo', 'transferencia')
            ->assertJsonPath('por_metodo_pago.0.etiqueta', 'Transferencia')
            ->assertJsonPath('por_metodo_pago.0.monto', 88000)
            ->assertJsonPath('por_metodo_pago.0.porcentaje', 53)
            ->assertJsonPath('por_metodo_pago.1.metodo', 'efectivo')
            ->assertJsonPath('por_metodo_pago.1.monto', 48000)
            ->assertJsonPath('por_metodo_pago.2.metodo', 'tarjeta');
    }

    public function test_ventas_sin_datos_devuelve_ceros(): void
    {
        $this->reporte($this->admin, 'ventas?'.self::SEMANA)
            ->assertOk()
            ->assertJsonPath('totales.facturas', 0)
            ->assertJsonPath('totales.ventas', 0)
            ->assertJsonPath('totales.ticket_promedio', 0)
            ->assertJsonPath('mejor_periodo', null)
            ->assertJsonCount(0, 'por_metodo_pago');
    }

    // --- RF34 Productos más vendidos -------------------------------------------

    private function datosDeProductos(): void
    {
        $this->factura('2026-11-02 12:00:00', [['Ajiaco', 4, 24000], ['Limonada', 2, 6000]]);
        $this->factura('2026-11-03 12:00:00', [['Ajiaco', 3, 24000], ['Langosta', 1, 150000]]);
        $this->factura('2026-11-04 12:00:00', [['Bandeja', 3, 30000]]);
        // Fuera del periodo y sin pagar: no cuentan.
        $this->factura('2026-11-10 12:00:00', [['Bandeja', 50, 30000]]);
        $this->factura(null, [['Bandeja', 50, 30000]], ['estado' => 'pendiente']);
    }

    public function test_productos_mas_vendidos_por_unidades(): void
    {
        $this->datosDeProductos();

        $r = $this->reporte($this->admin, 'productos-mas-vendidos?'.self::SEMANA)->assertOk();

        $r->assertJsonPath('ordenado_por', 'unidades')
            ->assertJsonCount(4, 'productos')
            ->assertJsonPath('productos.0.posicion', 1)
            ->assertJsonPath('productos.0.producto', 'Ajiaco')
            ->assertJsonPath('productos.0.unidades', 7)
            ->assertJsonPath('productos.0.ingresos', 168000)
            ->assertJsonPath('productos.0.facturas', 2)
            ->assertJsonPath('productos.1.producto', 'Bandeja')
            ->assertJsonPath('productos.1.unidades', 3)
            ->assertJsonPath('productos.3.producto', 'Langosta');

        // Total de ingresos: 168.000 + 90.000 + 12.000 + 150.000 = 420.000.
        $r->assertJsonPath('totales.ingresos', 420000)
            ->assertJsonPath('totales.unidades', 13)
            ->assertJsonPath('totales.productos_distintos', 4)
            ->assertJsonPath('productos.0.participacion', 40);
    }

    public function test_productos_mas_vendidos_por_ingresos(): void
    {
        $this->datosDeProductos();

        $r = $this->reporte($this->admin, 'productos-mas-vendidos?'.self::SEMANA.'&ordenar_por=ingresos')->assertOk();

        // La langosta vendió una sola unidad, pero es el segundo producto en ingresos.
        $r->assertJsonPath('ordenado_por', 'ingresos')
            ->assertJsonPath('productos.0.producto', 'Ajiaco')
            ->assertJsonPath('productos.1.producto', 'Langosta')
            ->assertJsonPath('productos.1.ingresos', 150000)
            ->assertJsonPath('productos.2.producto', 'Bandeja')
            ->assertJsonPath('productos.3.producto', 'Limonada');
    }

    public function test_limita_la_cantidad_de_productos_pero_los_totales_son_del_periodo(): void
    {
        $this->datosDeProductos();

        $this->reporte($this->admin, 'productos-mas-vendidos?'.self::SEMANA.'&limite=2')
            ->assertOk()
            ->assertJsonCount(2, 'productos')
            ->assertJsonPath('totales.productos_distintos', 4)
            ->assertJsonPath('totales.ingresos', 420000);
    }

    // --- RF35 Consumo de inventario --------------------------------------------

    private function movimiento(Insumo $insumo, TipoMovimiento $tipo, float $cantidad, string $local, ?float $costo = null): void
    {
        MovimientoInventario::create([
            'id_insumo' => $insumo->id_insumo,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_resultante' => 0,
            'costo_unitario' => $costo,
            'fecha' => $this->utc($local),
        ]);
    }

    public function test_consumo_de_inventario_por_insumo(): void
    {
        $pollo = Insumo::factory()->conStock(5, 1)->create(['nombre' => 'Pollo', 'costo_unitario' => 2000]);
        $sal = Insumo::factory()->conStock(0.5, 1)->create(['nombre' => 'Sal', 'costo_unitario' => 100]);

        // Pollo: sale 2, se devuelven 0,5 (pedido cancelado), merma 0,25, entra 10 a $3.000, ajuste -1.
        $this->movimiento($pollo, TipoMovimiento::CONSUMO_PEDIDO, -2, '2026-11-03 10:00:00');
        $this->movimiento($pollo, TipoMovimiento::REINTEGRO_PEDIDO, 0.5, '2026-11-03 10:30:00');
        $this->movimiento($pollo, TipoMovimiento::MERMA, -0.25, '2026-11-03 18:00:00');
        $this->movimiento($pollo, TipoMovimiento::ENTRADA, 10, '2026-11-04 08:00:00', 3000);
        $this->movimiento($pollo, TipoMovimiento::AJUSTE, -1, '2026-11-05 08:00:00');
        $this->movimiento($sal, TipoMovimiento::CONSUMO_PEDIDO, -3, '2026-11-03 10:00:00');
        // Fuera del periodo: no cuenta.
        $this->movimiento($pollo, TipoMovimiento::CONSUMO_PEDIDO, -100, '2026-11-20 10:00:00');

        $r = $this->reporte($this->admin, 'consumo-inventario?'.self::SEMANA)->assertOk();

        $r->assertJsonCount(2, 'insumos')
            // Ordenado por el valor consumido: pollo 1,5 kg × $2.000 = $3.000; sal 3 × $100 = $300.
            ->assertJsonPath('insumos.0.nombre', 'Pollo')
            ->assertJsonPath('insumos.0.unidad', 'kg')
            ->assertJsonPath('insumos.0.consumo', 1.5)
            ->assertJsonPath('insumos.0.valor_consumo', 3000)
            ->assertJsonPath('insumos.0.mermas', 0.25)
            ->assertJsonPath('insumos.0.valor_mermas', 500)
            ->assertJsonPath('insumos.0.entradas', 10)
            ->assertJsonPath('insumos.0.valor_entradas', 30000)
            ->assertJsonPath('insumos.0.ajustes', -1)
            ->assertJsonPath('insumos.0.bajo_minimo', false)
            ->assertJsonPath('insumos.1.nombre', 'Sal')
            ->assertJsonPath('insumos.1.consumo', 3)
            ->assertJsonPath('insumos.1.bajo_minimo', true)
            ->assertJsonPath('totales.valor_consumo', 3300)
            ->assertJsonPath('totales.valor_mermas', 500)
            ->assertJsonPath('totales.valor_entradas', 30000)
            ->assertJsonPath('totales.insumos_con_movimientos', 2)
            ->assertJsonPath('totales.insumos_bajo_minimo', 1);
    }

    public function test_el_cajero_consulta_el_consumo_de_inventario(): void
    {
        $insumo = Insumo::factory()->create(['nombre' => 'Arroz', 'costo_unitario' => 1000]);
        $this->movimiento($insumo, TipoMovimiento::CONSUMO_PEDIDO, -4, '2026-11-03 10:00:00');

        $this->reporte($this->cajero, 'consumo-inventario?'.self::SEMANA)
            ->assertOk()
            ->assertJsonPath('insumos.0.nombre', 'Arroz')
            ->assertJsonPath('insumos.0.valor_consumo', 4000);
    }

    // --- RF36 Ocupación de mesas -----------------------------------------------

    private function pedidoDeMesa(?Mesa $mesa, string $creadoLocal, array $estado = [EstadoPedido::ENTREGADO]): Pedido
    {
        return Pedido::factory()
            ->enEstado($estado[0])
            ->create(['id_mesa' => $mesa?->id_mesa, 'fecha_creacion' => $this->utc($creadoLocal)]);
    }

    public function test_ocupacion_de_mesas(): void
    {
        $mesa1 = Mesa::factory()->create(['numero' => 1]);
        $mesa2 = Mesa::factory()->create(['numero' => 2]);
        $baja = Mesa::factory()->create(['numero' => 3]);
        $baja->forceFill(['activo' => false])->save();

        // Mesa 1: pedido de 12:00 a 14:00 (hora del pago).
        $p1 = $this->pedidoDeMesa($mesa1, '2026-11-02 12:00:00', [EstadoPedido::FACTURADO]);
        $this->factura('2026-11-02 14:00:00', [['Ajiaco', 1, 24000]], ['pedido' => $p1]);
        // Mesa 2: pedido de 18:00 a 19:30 (entregado, sin factura: termina al entregarse).
        $p2 = $this->pedidoDeMesa($mesa2, '2026-11-02 18:00:00');
        $p2->forceFill(['fecha_entrega' => $this->utc('2026-11-02 19:30:00')])->save();

        // No cuentan: cancelado, sin mesa (para recoger) y mesa dada de baja.
        $this->pedidoDeMesa($mesa1, '2026-11-03 12:00:00', [EstadoPedido::CANCELADO]);
        $this->pedidoDeMesa(null, '2026-11-03 13:00:00');
        $this->pedidoDeMesa($baja, '2026-11-03 14:00:00');

        $r = $this->reporte($this->cajero, 'ocupacion-mesas?'.self::SEMANA)->assertOk();

        // 12 h de operación × 7 días = 84 h disponibles por mesa.
        $r->assertJsonPath('resumen.mesas', 2)
            ->assertJsonPath('resumen.pedidos', 2)
            ->assertJsonPath('resumen.sesiones', 2)
            ->assertJsonPath('resumen.horas_ocupadas', 3.5)
            ->assertJsonPath('resumen.ocupacion_promedio', 2.1)
            ->assertJsonPath('resumen.permanencia_promedio_min', 105)
            ->assertJsonCount(2, 'mesas')
            ->assertJsonPath('mesas.0.numero', 1)
            ->assertJsonPath('mesas.0.horas_ocupada', 2)
            ->assertJsonPath('mesas.0.ocupacion', 2.4)
            ->assertJsonPath('mesas.1.numero', 2)
            ->assertJsonPath('mesas.1.horas_ocupada', 1.5)
            ->assertJsonPath('mesas.1.ocupacion', 1.8);

        // Lunes (índice 0) 12:00 y 13:00: 1 de 2 mesas ocupada = 50 %.
        $r->assertJsonCount(7, 'mapa_calor')
            ->assertJsonCount(24, 'mapa_calor.0')
            ->assertJsonPath('mapa_calor.0.12', 50)
            ->assertJsonPath('mapa_calor.0.13', 50)
            ->assertJsonPath('mapa_calor.0.14', 0);
    }

    public function test_ocupacion_incluye_el_resumen_de_reservas(): void
    {
        $mesa = Mesa::factory()->create(['numero' => 1]);
        foreach (['cumplida', 'cumplida', 'cancelada', 'no_asistio', 'pendiente'] as $estado) {
            Reserva::factory()->create(['id_mesa' => $mesa->id_mesa, 'fecha' => '2026-11-03', 'estado' => $estado]);
        }
        // Fuera del periodo.
        Reserva::factory()->create(['id_mesa' => $mesa->id_mesa, 'fecha' => '2026-11-20', 'estado' => 'cumplida']);

        $this->reporte($this->admin, 'ocupacion-mesas?'.self::SEMANA)
            ->assertOk()
            ->assertJsonPath('reservas.total', 5)
            ->assertJsonPath('reservas.cumplidas', 2)
            ->assertJsonPath('reservas.canceladas', 1)
            ->assertJsonPath('reservas.no_asistio', 1)
            ->assertJsonPath('reservas.pendientes', 1);
    }

    public function test_ocupacion_sin_mesas_ni_pedidos(): void
    {
        $this->reporte($this->admin, 'ocupacion-mesas?'.self::SEMANA)
            ->assertOk()
            ->assertJsonPath('resumen.mesas', 0)
            ->assertJsonPath('resumen.ocupacion_promedio', 0)
            ->assertJsonCount(0, 'mesas');
    }

    // --- RF37 Indicadores financieros ------------------------------------------

    public function test_indicadores_compara_contra_el_periodo_anterior(): void
    {
        // Periodo anterior (2 al 8 de nov): una venta de $60.000.
        $this->factura('2026-11-03 12:00:00', [['Ajiaco', 1, 60000]]);
        // Periodo actual (9 al 15 de nov): $90.000 y un costo de insumos de $10.000.
        $this->factura('2026-11-10 12:00:00', [['Ajiaco', 1, 90000]]);
        $insumo = Insumo::factory()->create(['nombre' => 'Pollo', 'costo_unitario' => 1000]);
        $this->movimiento($insumo, TipoMovimiento::CONSUMO_PEDIDO, -10, '2026-11-10 10:00:00');
        $this->movimiento($insumo, TipoMovimiento::MERMA, -1, '2026-11-11 10:00:00');

        $r = $this->reporte($this->admin, 'indicadores-financieros?desde=2026-11-09&hasta=2026-11-15')->assertOk();

        $r->assertJsonPath('periodo.dias', 7)
            ->assertJsonPath('periodo_anterior.desde', '2026-11-02')
            ->assertJsonPath('periodo_anterior.hasta', '2026-11-08')
            ->assertJsonPath('kpis.ventas_totales.valor', 90000)
            ->assertJsonPath('kpis.ventas_totales.anterior', 60000)
            ->assertJsonPath('kpis.ventas_totales.variacion', 50)
            ->assertJsonPath('kpis.ventas_totales.variacion_tipo', 'porcentaje')
            ->assertJsonPath('kpis.facturas.valor', 1)
            ->assertJsonPath('kpis.ticket_promedio.valor', 90000)
            // Impuesto 8 % incluido: 90.000 × 8 / 108 = 6.666,67 → ventas netas 83.333,33.
            ->assertJsonPath('kpis.impuestos.valor', 6666.67)
            ->assertJsonPath('kpis.ventas_netas.valor', 83333.33)
            ->assertJsonPath('kpis.costo_insumos.valor', 10000)
            ->assertJsonPath('kpis.mermas.valor', 1000)
            // Margen: 83.333,33 − 10.000 = 73.333,33 (88 % de las ventas netas).
            ->assertJsonPath('kpis.margen_bruto.valor', 73333.33)
            ->assertJsonPath('kpis.margen_porcentaje.valor', 88)
            // Antes no hubo costo: margen del 100 % → baja 12 puntos.
            ->assertJsonPath('kpis.margen_porcentaje.anterior', 100)
            ->assertJsonPath('kpis.margen_porcentaje.variacion', -12)
            ->assertJsonPath('kpis.margen_porcentaje.variacion_tipo', 'puntos');
    }

    public function test_indicadores_sin_periodo_anterior_no_calcula_variacion(): void
    {
        $this->factura('2026-11-10 12:00:00', [['Ajiaco', 1, 90000]]);

        $this->reporte($this->admin, 'indicadores-financieros?desde=2026-11-09&hasta=2026-11-15')
            ->assertOk()
            ->assertJsonPath('kpis.ventas_totales.valor', 90000)
            ->assertJsonPath('kpis.ventas_totales.anterior', 0)
            ->assertJsonPath('kpis.ventas_totales.variacion', null);
    }

    public function test_indicadores_cuentas_por_cobrar_y_anulaciones(): void
    {
        // Factura pendiente de 20.000 con un abono de 5.000: debe 15.000.
        $this->factura(null, [['Ajiaco', 1, 20000]], ['estado' => 'pendiente', 'abono' => 5000]);
        // Pedido entregado sin factura (2 × 10.000).
        $pedido = Pedido::factory()->enEstado(EstadoPedido::ENTREGADO)->create();
        \App\Models\DetallePedido::factory()->create(['id_pedido' => $pedido->id_pedido, 'cantidad' => 2, 'precio_unitario' => 10000]);
        // Una anulación dentro del periodo y otra fuera.
        $this->factura(null, [['Ajiaco', 1, 7000]], ['estado' => 'anulada', 'fecha_anulacion' => '2026-11-10 09:00:00']);
        $this->factura(null, [['Ajiaco', 1, 9000]], ['estado' => 'anulada', 'fecha_anulacion' => '2026-10-01 09:00:00']);

        $this->reporte($this->admin, 'indicadores-financieros?desde=2026-11-09&hasta=2026-11-15')
            ->assertOk()
            ->assertJsonPath('por_cobrar.facturas_pendientes', 1)
            ->assertJsonPath('por_cobrar.saldo_facturas', 15000)
            ->assertJsonPath('por_cobrar.pedidos_sin_facturar', 1)
            ->assertJsonPath('por_cobrar.valor_pedidos_sin_facturar', 20000)
            ->assertJsonPath('anuladas.cantidad', 1)
            ->assertJsonPath('anuladas.total', 7000);
    }
}
