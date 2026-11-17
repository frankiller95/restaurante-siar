<?php

namespace App\Http\Controllers\Api;

use App\Enums\AgrupacionPeriodo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporte\ConsultaReporteRequest;
use App\Reportes\RangoFechas;
use App\Services\ReporteService;
use Illuminate\Http\JsonResponse;

/**
 * Módulo 7: RF33 a RF37. Ventas, productos e indicadores requieren el permiso
 * "ver_reportes"; inventario y ocupación también los ve quien tenga
 * "ver_reportes_operativos" (ver routes/api.php).
 */
class ReporteController extends Controller
{
    public function __construct(private readonly ReporteService $reportes) {}

    /** RF33: ventas por día, semana o mes. */
    public function ventas(ConsultaReporteRequest $request): JsonResponse
    {
        $agrupar = AgrupacionPeriodo::tryFrom((string) $request->validated('agrupar')) ?? AgrupacionPeriodo::DIA;

        return response()->json($this->reportes->ventasPorPeriodo($this->rango($request), $agrupar));
    }

    /** RF34: productos más vendidos. */
    public function productos(ConsultaReporteRequest $request): JsonResponse
    {
        return response()->json($this->reportes->productosMasVendidos(
            $this->rango($request),
            $request->validated('ordenar_por') ?? 'unidades',
            (int) ($request->validated('limite') ?? 10),
        ));
    }

    /** RF35: consumo de inventario (se alimenta del kárdex). */
    public function inventario(ConsultaReporteRequest $request): JsonResponse
    {
        return response()->json($this->reportes->consumoInventario($this->rango($request)));
    }

    /** RF36: ocupación de mesas. */
    public function ocupacion(ConsultaReporteRequest $request): JsonResponse
    {
        return response()->json($this->reportes->ocupacionMesas($this->rango($request)));
    }

    /** RF37: indicadores financieros con comparación contra el periodo anterior. */
    public function indicadores(ConsultaReporteRequest $request): JsonResponse
    {
        return response()->json($this->reportes->indicadoresFinancieros($this->rango($request)));
    }

    private function rango(ConsultaReporteRequest $request): RangoFechas
    {
        return RangoFechas::desdeTexto($request->validated('desde'), $request->validated('hasta'));
    }
}
