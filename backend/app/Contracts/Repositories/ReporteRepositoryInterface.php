<?php

namespace App\Contracts\Repositories;

use App\Reportes\RangoFechas;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Consultas de solo lectura del Módulo 7 (Reportes). Devuelve filas planas; los
 * cálculos y la agrupación por periodo los hace ReporteService.
 *
 * Todas las fechas de las filas están en UTC (como las guarda la base).
 */
interface ReporteRepositoryInterface
{
    /**
     * Facturas pagadas cuyo pago se completó en el periodo, una fila por factura
     * (se recorre de a una para no cargar todo en memoria).
     *
     * @return LazyCollection<int, object{id_factura: int, fecha_pago: string, subtotal: string, descuento_promociones: string, descuento: string, impuesto: string, propina: string, total: string}>
     */
    public function facturasPagadas(RangoFechas $rango): LazyCollection;

    /** @return Collection<int, object{metodo: string, monto: string, cantidad: int}> pagos del periodo por método */
    public function pagosPorMetodo(RangoFechas $rango): Collection;

    /** @return Collection<int, object{id_producto: int|null, descripcion: string, categoria: string|null, unidades: string, ingresos: string, facturas: int}> */
    public function lineasVendidas(RangoFechas $rango): Collection;

    /** @return Collection<int, object> movimientos del kárdex agrupados por insumo y tipo, con su valor */
    public function movimientosPorInsumo(RangoFechas $rango): Collection;

    /**
     * Pedidos de mesa (no cancelados) iniciados en el periodo. "fin" es null si el
     * pedido sigue abierto.
     *
     * @return Collection<int, array{id_pedido: int, id_mesa: int, inicio: string, fin: string|null}>
     */
    public function visitasDeMesas(RangoFechas $rango): Collection;

    /** @return Collection<int, array{id_mesa: int, numero: int, capacidad: int, ubicacion: string|null}> */
    public function mesasActivas(): Collection;

    /** @return array<string, int> reservas del periodo por estado */
    public function reservasPorEstado(RangoFechas $rango): array;

    /** @return array{cantidad: int, total: float} facturas anuladas en el periodo */
    public function facturasAnuladas(RangoFechas $rango): array;

    /** @return array{facturas_pendientes: int, saldo_facturas: float, pedidos_sin_facturar: int, valor_pedidos_sin_facturar: float} */
    public function cuentasPorCobrar(): array;
}
