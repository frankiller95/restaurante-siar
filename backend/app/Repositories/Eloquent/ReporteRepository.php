<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ReporteRepositoryInterface;
use App\Enums\EstadoFactura;
use App\Enums\EstadoPedido;
use App\Reportes\RangoFechas;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Consultas de reportes con el constructor de consultas (agregaciones en SQL).
 * Todo es de solo lectura.
 */
class ReporteRepository implements ReporteRepositoryInterface
{
    public function facturasPagadas(RangoFechas $rango): LazyCollection
    {
        return DB::table('facturas')
            ->select('id_factura', 'fecha_pago', 'subtotal', 'descuento_promociones', 'descuento', 'impuesto', 'propina', 'total')
            ->where('estado', EstadoFactura::PAGADA->value)
            ->whereBetween('fecha_pago', [$rango->desdeUtc(), $rango->hastaUtc()])
            ->orderBy('fecha_pago')
            ->orderBy('id_factura')
            ->cursor();
    }

    public function pagosPorMetodo(RangoFechas $rango): Collection
    {
        return DB::table('pagos as p')
            ->join('facturas as f', 'f.id_factura', '=', 'p.id_factura')
            ->where('f.estado', '!=', EstadoFactura::ANULADA->value)
            ->whereBetween('p.fecha', [$rango->desdeUtc(), $rango->hastaUtc()])
            ->groupBy('p.metodo')
            ->selectRaw('p.metodo, SUM(p.monto) as monto, COUNT(*) as cantidad')
            ->get();
    }

    public function lineasVendidas(RangoFechas $rango): Collection
    {
        return DB::table('detalle_facturas as d')
            ->join('facturas as f', 'f.id_factura', '=', 'd.id_factura')
            ->leftJoin('productos as pr', 'pr.id_producto', '=', 'd.id_producto')
            ->leftJoin('categorias as c', 'c.id_categoria', '=', 'pr.id_categoria')
            ->where('f.estado', EstadoFactura::PAGADA->value)
            ->whereBetween('f.fecha_pago', [$rango->desdeUtc(), $rango->hastaUtc()])
            ->groupBy('d.id_producto', 'd.descripcion', 'c.nombre')
            ->selectRaw('d.id_producto, d.descripcion, c.nombre as categoria, SUM(d.cantidad) as unidades, SUM(d.subtotal) as ingresos, COUNT(DISTINCT d.id_factura) as facturas')
            ->get();
    }

    public function movimientosPorInsumo(RangoFechas $rango): Collection
    {
        // El valor usa el costo de la entrada; en consumos y mermas (sin costo propio),
        // el costo actual del insumo.
        return DB::table('movimientos_inventario as m')
            ->join('insumos as i', 'i.id_insumo', '=', 'm.id_insumo')
            ->whereBetween('m.fecha', [$rango->desdeUtc(), $rango->hastaUtc()])
            ->groupBy('m.id_insumo', 'i.nombre', 'i.unidad_medida', 'i.costo_unitario', 'i.stock_actual', 'i.stock_minimo', 'i.activo', 'm.tipo')
            ->selectRaw('m.id_insumo, i.nombre, i.unidad_medida, i.costo_unitario, i.stock_actual, i.stock_minimo, i.activo, m.tipo, SUM(m.cantidad) as cantidad, SUM(m.cantidad * COALESCE(m.costo_unitario, i.costo_unitario)) as valor')
            ->get();
    }

    public function visitasDeMesas(RangoFechas $rango): Collection
    {
        $pagoDelPedido = DB::table('facturas as f')
            ->selectRaw('MAX(f.fecha_pago)')
            ->whereColumn('f.id_pedido', 'p.id_pedido')
            ->where('f.estado', EstadoFactura::PAGADA->value);

        return DB::table('pedidos as p')
            ->select('p.id_pedido', 'p.id_mesa', 'p.estado', 'p.fecha_creacion', 'p.fecha_entrega')
            ->selectSub($pagoDelPedido, 'fecha_pago')
            ->whereNotNull('p.id_mesa')
            ->where('p.estado', '!=', EstadoPedido::CANCELADO->value)
            ->whereBetween('p.fecha_creacion', [$rango->desdeUtc(), $rango->hastaUtc()])
            ->orderBy('p.fecha_creacion')
            ->get()
            ->map(fn ($fila) => [
                'id_pedido' => (int) $fila->id_pedido,
                'id_mesa' => (int) $fila->id_mesa,
                'inicio' => $fila->fecha_creacion,
                // La mesa se libera cuando se paga; si no hay pago, cuando se entregó.
                'fin' => $fila->fecha_pago ?? $fila->fecha_entrega,
            ]);
    }

    public function mesasActivas(): Collection
    {
        return DB::table('mesas')
            ->where('activo', true)
            ->orderBy('numero')
            ->get(['id_mesa', 'numero', 'capacidad', 'ubicacion'])
            ->map(fn ($m) => [
                'id_mesa' => (int) $m->id_mesa,
                'numero' => (int) $m->numero,
                'capacidad' => (int) $m->capacidad,
                'ubicacion' => $m->ubicacion,
            ]);
    }

    public function reservasPorEstado(RangoFechas $rango): array
    {
        return DB::table('reservas')
            ->whereBetween('fecha', [$rango->desde->format('Y-m-d'), $rango->hasta->format('Y-m-d')])
            ->groupBy('estado')
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->pluck('cantidad', 'estado')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public function facturasAnuladas(RangoFechas $rango): array
    {
        $fila = DB::table('facturas')
            ->where('estado', EstadoFactura::ANULADA->value)
            ->whereBetween('fecha_anulacion', [$rango->desdeUtc(), $rango->hastaUtc()])
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->first();

        return ['cantidad' => (int) $fila->cantidad, 'total' => (float) $fila->total];
    }

    public function cuentasPorCobrar(): array
    {
        $pendientes = DB::table('facturas')
            ->where('estado', EstadoFactura::PENDIENTE->value)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->first();

        $abonado = (float) DB::table('pagos as p')
            ->join('facturas as f', 'f.id_factura', '=', 'p.id_factura')
            ->where('f.estado', EstadoFactura::PENDIENTE->value)
            ->sum('p.monto');

        // Pedidos entregados que todavía no tienen una factura vigente.
        $sinFactura = fn (): Builder => DB::table('pedidos as p')
            ->where('p.estado', EstadoPedido::ENTREGADO->value)
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')
                ->from('facturas as f')
                ->whereColumn('f.id_pedido', 'p.id_pedido')
                ->whereIn('f.estado', EstadoFactura::valoresVigentes()));

        $productos = (float) DB::table('detalle_pedidos as d')
            ->whereIn('d.id_pedido', $sinFactura()->select('p.id_pedido'))
            ->selectRaw('COALESCE(SUM(d.cantidad * d.precio_unitario - COALESCE(d.descuento, 0)), 0) as valor')
            ->value('valor');

        return [
            'facturas_pendientes' => (int) $pendientes->cantidad,
            'saldo_facturas' => round((float) $pendientes->total - $abonado, 2),
            'pedidos_sin_facturar' => $sinFactura()->count(),
            'valor_pedidos_sin_facturar' => round($productos + (float) $sinFactura()->sum('p.propina'), 2),
        ];
    }
}
