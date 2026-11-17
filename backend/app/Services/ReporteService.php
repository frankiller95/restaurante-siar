<?php

namespace App\Services;

use App\Contracts\Repositories\ReporteRepositoryInterface;
use App\Enums\AgrupacionPeriodo;
use App\Enums\MetodoPago;
use App\Enums\TipoMovimiento;
use App\Enums\UnidadMedida;
use App\Reportes\CalculadoraOcupacion;
use App\Reportes\RangoFechas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Módulo 7: RF33 Ventas por periodo, RF34 Productos más vendidos, RF35 Consumo
 * de inventario, RF36 Ocupación de mesas y RF37 Indicadores financieros.
 *
 * Todos son de solo lectura. Una venta cuenta cuando la factura queda pagada
 * (fecha de pago); las facturas pendientes y anuladas no son ventas.
 */
class ReporteService
{
    public function __construct(private readonly ReporteRepositoryInterface $reportes) {}

    // --- RF33 ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function ventasPorPeriodo(RangoFechas $rango, AgrupacionPeriodo $agrupar): array
    {
        $zona = config('siar.zona_horaria');

        // Todos los periodos del rango, incluso sin ventas, para que la serie no tenga huecos.
        $series = [];
        for ($inicio = $agrupar->inicio($rango->desde); $inicio->lte($rango->hasta); $inicio = $agrupar->siguiente($inicio)) {
            $series[$agrupar->clave($inicio)] = ['clave' => $agrupar->clave($inicio), 'etiqueta' => $agrupar->etiqueta($inicio)] + $this->acumuladorVentas();
        }

        $totales = $this->acumuladorVentas();

        foreach ($this->reportes->facturasPagadas($rango) as $factura) {
            $fecha = CarbonImmutable::parse($factura->fecha_pago, 'UTC')->setTimezone($zona);
            $clave = $agrupar->clave($agrupar->inicio($fecha));

            if (isset($series[$clave])) {
                $this->sumarVenta($series[$clave], $factura);
            }
            $this->sumarVenta($totales, $factura);
        }

        $series = array_values(array_map(fn (array $s) => $this->cerrarVentas($s), $series));
        $totales = $this->cerrarVentas($totales);

        $mejor = collect($series)->where('ventas', '>', 0)->sortByDesc('ventas')->first();

        return [
            'periodo' => $rango->aArray(),
            'agrupacion' => $agrupar->value,
            'series' => $series,
            'totales' => $totales,
            'mejor_periodo' => $mejor ? ['etiqueta' => $mejor['etiqueta'], 'ventas' => $mejor['ventas']] : null,
            'por_metodo_pago' => $this->metodosDePago($rango),
        ];
    }

    // --- RF34 ------------------------------------------------------------------

    /**
     * @param  'unidades'|'ingresos'  $ordenarPor
     * @return array<string, mixed>
     */
    public function productosMasVendidos(RangoFechas $rango, string $ordenarPor = 'unidades', int $limite = 10): array
    {
        // Un producto renombrado aparece con dos descripciones: se une por id y se
        // conserva el nombre con más unidades vendidas.
        $productos = [];
        foreach ($this->reportes->lineasVendidas($rango) as $linea) {
            $clave = $linea->id_producto !== null ? 'p'.$linea->id_producto : 'd'.$linea->descripcion;
            $unidades = (int) $linea->unidades;

            $productos[$clave] ??= [
                'id_producto' => $linea->id_producto !== null ? (int) $linea->id_producto : null,
                'producto' => $linea->descripcion,
                'categoria' => $linea->categoria,
                'unidades' => 0,
                'ingresos' => 0.0,
                'facturas' => 0,
                '_mayor' => 0,
            ];

            if ($unidades > $productos[$clave]['_mayor']) {
                $productos[$clave]['producto'] = $linea->descripcion;
                $productos[$clave]['_mayor'] = $unidades;
            }
            $productos[$clave]['unidades'] += $unidades;
            $productos[$clave]['ingresos'] += (float) $linea->ingresos;
            $productos[$clave]['facturas'] += (int) $linea->facturas;
        }

        $ingresosTotales = array_sum(array_column($productos, 'ingresos'));
        $unidadesTotales = array_sum(array_column($productos, 'unidades'));

        $campo = $ordenarPor === 'ingresos' ? 'ingresos' : 'unidades';
        $otro = $campo === 'ingresos' ? 'unidades' : 'ingresos';
        // Desempate: la otra medida y, al final, el nombre.
        $lista = collect($productos)
            ->sort(fn (array $a, array $b) => [$b[$campo], $b[$otro], $a['producto']] <=> [$a[$campo], $a[$otro], $b['producto']])
            ->take($limite)
            ->values()
            ->map(function (array $p, int $i) use ($ingresosTotales) {
                unset($p['_mayor']);
                $participacion = $ingresosTotales > 0 ? round($p['ingresos'] / $ingresosTotales * 100, 1) : 0.0;
                $p['ingresos'] = round($p['ingresos'], 2);

                return ['posicion' => $i + 1] + $p + ['participacion' => $participacion];
            })
            ->all();

        return [
            'periodo' => $rango->aArray(),
            'ordenado_por' => $campo,
            'productos' => $lista,
            'totales' => [
                'productos_distintos' => count($productos),
                'unidades' => (int) $unidadesTotales,
                'ingresos' => round($ingresosTotales, 2),
            ],
        ];
    }

    // --- RF35 ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function consumoInventario(RangoFechas $rango): array
    {
        $insumos = $this->movimientosAgrupados($this->reportes->movimientosPorInsumo($rango));

        $lista = collect($insumos)
            ->sort(fn (array $a, array $b) => [$b['valor_consumo'], $a['nombre']] <=> [$a['valor_consumo'], $b['nombre']])
            ->values()
            ->all();

        return [
            'periodo' => $rango->aArray(),
            'insumos' => $lista,
            'totales' => [
                'insumos_con_movimientos' => count($lista),
                'valor_consumo' => round(array_sum(array_column($lista, 'valor_consumo')), 2),
                'valor_mermas' => round(array_sum(array_column($lista, 'valor_mermas')), 2),
                'valor_entradas' => round(array_sum(array_column($lista, 'valor_entradas')), 2),
                'insumos_bajo_minimo' => count(array_filter($lista, fn (array $i) => $i['bajo_minimo'])),
            ],
        ];
    }

    // --- RF36 ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function ocupacionMesas(RangoFechas $rango, ?CarbonImmutable $ahora = null): array
    {
        $ahora ??= CarbonImmutable::now();
        $zona = config('siar.zona_horaria');

        $visitas = $this->reportes->visitasDeMesas($rango)->map(fn (array $v) => [
            'id_mesa' => $v['id_mesa'],
            'inicio' => CarbonImmutable::parse($v['inicio'], 'UTC'),
            // Sin fin = pedido todavía abierto: cuenta hasta ahora (con el tope por pedido).
            'fin' => $v['fin'] ? CarbonImmutable::parse($v['fin'], 'UTC') : $ahora,
        ])->all();

        $calculadora = new CalculadoraOcupacion(
            $zona,
            (float) config('siar.reportes.horas_operacion'),
            (float) config('siar.reportes.maximo_horas_por_pedido'),
        );

        $resultado = $calculadora->calcular($visitas, $this->reportes->mesasActivas()->all(), $rango, $ahora);

        $reservas = $this->reportes->reservasPorEstado($rango);

        return [
            'periodo' => $rango->aArray(),
            'horas_operacion' => (float) config('siar.reportes.horas_operacion'),
            'resumen' => $resultado['resumen'],
            'mesas' => $resultado['mesas'],
            'mapa_calor' => $resultado['mapa_calor'],
            'reservas' => [
                'total' => array_sum($reservas),
                'cumplidas' => $reservas['cumplida'] ?? 0,
                'canceladas' => $reservas['cancelada'] ?? 0,
                'no_asistio' => $reservas['no_asistio'] ?? 0,
                'pendientes' => $reservas['pendiente'] ?? 0,
            ],
        ];
    }

    // --- RF37 ------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function indicadoresFinancieros(RangoFechas $rango): array
    {
        $anterior = $rango->anterior();
        $actual = $this->indicadoresDelPeriodo($rango);
        $previo = $this->indicadoresDelPeriodo($anterior);

        $kpis = [];
        foreach ($actual as $nombre => $valor) {
            $enPuntos = $nombre === 'margen_porcentaje';
            $antes = $previo[$nombre];

            $kpis[$nombre] = [
                'valor' => $valor,
                'anterior' => $antes,
                // El margen se compara en puntos porcentuales; el resto, en %.
                'variacion' => $enPuntos
                    ? round($valor - $antes, 1)
                    : ($antes != 0 ? round(($valor - $antes) / abs($antes) * 100, 1) : null),
                'variacion_tipo' => $enPuntos ? 'puntos' : 'porcentaje',
            ];
        }

        return [
            'periodo' => $rango->aArray(),
            'periodo_anterior' => $anterior->aArray(),
            'kpis' => $kpis,
            'metodos_pago' => $this->metodosDePago($rango),
            'anuladas' => $this->reportes->facturasAnuladas($rango),
            'por_cobrar' => $this->reportes->cuentasPorCobrar(),
            // El costo se valora con el costo actual de cada insumo (los consumos no guardan su costo).
            'nota_costos' => 'El costo de insumos se calcula con el costo unitario actual de cada insumo.',
        ];
    }

    // --- Apoyo -----------------------------------------------------------------

    /** @return array<string, int|float> */
    private function acumuladorVentas(): array
    {
        return ['facturas' => 0, 'ventas' => 0.0, 'ventas_netas' => 0.0, 'descuentos' => 0.0, 'impuestos' => 0.0, 'propinas' => 0.0];
    }

    /** @param array<string, int|float> $acumulado */
    private function sumarVenta(array &$acumulado, object $factura): void
    {
        $acumulado['facturas']++;
        $acumulado['ventas'] += (float) $factura->total;
        // Ventas netas = lo vendido sin impuesto ni propina (la propina no es ingreso del restaurante por productos).
        $acumulado['ventas_netas'] += (float) $factura->total - (float) $factura->impuesto - (float) $factura->propina;
        $acumulado['descuentos'] += (float) $factura->descuento_promociones + (float) $factura->descuento;
        $acumulado['impuestos'] += (float) $factura->impuesto;
        $acumulado['propinas'] += (float) $factura->propina;
    }

    /**
     * @param  array<string, mixed>  $acumulado
     * @return array<string, mixed>
     */
    private function cerrarVentas(array $acumulado): array
    {
        foreach (['ventas', 'ventas_netas', 'descuentos', 'impuestos', 'propinas'] as $campo) {
            $acumulado[$campo] = round($acumulado[$campo], 2);
        }
        $acumulado['ticket_promedio'] = $acumulado['facturas'] > 0 ? round($acumulado['ventas'] / $acumulado['facturas'], 2) : 0.0;

        return $acumulado;
    }

    /** @return list<array{metodo: string, etiqueta: string, monto: float, cantidad: int, porcentaje: float}> */
    private function metodosDePago(RangoFechas $rango): array
    {
        $filas = $this->reportes->pagosPorMetodo($rango);
        $total = (float) $filas->sum('monto');

        return $filas
            ->sortByDesc(fn ($f) => (float) $f->monto)
            ->map(fn ($f) => [
                'metodo' => $f->metodo,
                'etiqueta' => MetodoPago::tryFrom($f->metodo)?->etiqueta() ?? $f->metodo,
                'monto' => round((float) $f->monto, 2),
                'cantidad' => (int) $f->cantidad,
                'porcentaje' => $total > 0 ? round((float) $f->monto / $total * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Junta los movimientos de cada insumo. En el kárdex el consumo va en negativo y el
     * reintegro en positivo: el consumo real es lo que sale menos lo que se devuelve.
     *
     * @param  Collection<int, object>  $movimientos
     * @return array<int, array<string, mixed>>
     */
    private function movimientosAgrupados(Collection $movimientos): array
    {
        $insumos = [];

        foreach ($movimientos as $m) {
            $i = &$insumos[$m->id_insumo];
            $i ??= [
                'id_insumo' => (int) $m->id_insumo,
                'nombre' => $m->nombre,
                'unidad' => UnidadMedida::tryFrom($m->unidad_medida)?->abreviatura() ?? $m->unidad_medida,
                'costo_unitario' => (float) $m->costo_unitario,
                'stock_actual' => (float) $m->stock_actual,
                'stock_minimo' => (float) $m->stock_minimo,
                'bajo_minimo' => (bool) $m->activo && (float) $m->stock_actual <= (float) $m->stock_minimo,
                'consumo' => 0.0, 'valor_consumo' => 0.0,
                'mermas' => 0.0, 'valor_mermas' => 0.0,
                'entradas' => 0.0, 'valor_entradas' => 0.0,
                'ajustes' => 0.0,
            ];

            $cantidad = (float) $m->cantidad;
            $valor = (float) $m->valor;

            switch (TipoMovimiento::tryFrom($m->tipo)) {
                case TipoMovimiento::CONSUMO_PEDIDO:
                case TipoMovimiento::REINTEGRO_PEDIDO:
                    $i['consumo'] -= $cantidad;
                    $i['valor_consumo'] -= $valor;
                    break;
                case TipoMovimiento::MERMA:
                    $i['mermas'] -= $cantidad;
                    $i['valor_mermas'] -= $valor;
                    break;
                case TipoMovimiento::ENTRADA:
                    $i['entradas'] += $cantidad;
                    $i['valor_entradas'] += $valor;
                    break;
                case TipoMovimiento::AJUSTE:
                    $i['ajustes'] += $cantidad;
                    break;
            }
            unset($i);
        }

        return array_map(function (array $i) {
            foreach (['consumo', 'mermas', 'entradas', 'ajustes'] as $campo) {
                $i[$campo] = round($i[$campo], 3);
            }
            foreach (['valor_consumo', 'valor_mermas', 'valor_entradas'] as $campo) {
                $i[$campo] = round($i[$campo], 2);
            }

            return $i;
        }, $insumos);
    }

    /** @return array<string, int|float> cifras del periodo que se comparan con el anterior */
    private function indicadoresDelPeriodo(RangoFechas $rango): array
    {
        $ventas = $this->acumuladorVentas();
        foreach ($this->reportes->facturasPagadas($rango) as $factura) {
            $this->sumarVenta($ventas, $factura);
        }
        $ventas = $this->cerrarVentas($ventas);

        $insumos = $this->movimientosAgrupados($this->reportes->movimientosPorInsumo($rango));
        $costo = round(array_sum(array_column($insumos, 'valor_consumo')), 2);
        $mermas = round(array_sum(array_column($insumos, 'valor_mermas')), 2);
        $margen = round($ventas['ventas_netas'] - $costo, 2);

        return [
            'ventas_totales' => $ventas['ventas'],
            'ventas_netas' => $ventas['ventas_netas'],
            'facturas' => $ventas['facturas'],
            'ticket_promedio' => $ventas['ticket_promedio'],
            'descuentos' => $ventas['descuentos'],
            'impuestos' => $ventas['impuestos'],
            'propinas' => $ventas['propinas'],
            'costo_insumos' => $costo,
            'mermas' => $mermas,
            'margen_bruto' => $margen,
            'margen_porcentaje' => $ventas['ventas_netas'] > 0 ? round($margen / $ventas['ventas_netas'] * 100, 1) : 0.0,
        ];
    }
}
