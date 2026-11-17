<?php

namespace App\Http\Requests\Reporte;

use App\Enums\AgrupacionPeriodo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parámetros comunes de los reportes (RF33 a RF37). Sin fechas se toman los
 * últimos 30 días; el máximo de días lo valida RangoFechas.
 */
class ConsultaReporteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'agrupar' => ['nullable', Rule::enum(AgrupacionPeriodo::class)],
            'ordenar_por' => ['nullable', Rule::in(['unidades', 'ingresos'])],
            'limite' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'desde' => 'fecha inicial',
            'hasta' => 'fecha final',
            'agrupar' => 'agrupación',
            'ordenar_por' => 'orden',
            'limite' => 'cantidad de productos',
        ];
    }
}
