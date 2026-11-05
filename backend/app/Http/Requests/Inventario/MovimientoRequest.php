<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF25 entrada, RF26 merma y ajuste. Las reglas cambian según la ruta:
 * - entradas: cantidad, costo_unitario y proveedor opcionales, fecha opcional (no futura).
 * - mermas: cantidad y motivo obligatorio.
 * - ajustes: stock_contado y motivo obligatorio.
 */
class MovimientoRequest extends FormRequest
{
    public function rules(): array
    {
        $ruta = $this->route()?->getName();

        return match ($ruta) {
            'insumos.entradas' => [
                'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999'],
                'costo_unitario' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
                'proveedor' => ['nullable', 'string', 'max:120'],
                'fecha' => ['nullable', 'date', 'before_or_equal:now'],
            ],
            'insumos.mermas' => [
                'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999'],
                'motivo' => ['required', 'string', 'min:3', 'max:255'],
            ],
            default => [
                'stock_contado' => ['required', 'numeric', 'min:0', 'max:999999'],
                'motivo' => ['required', 'string', 'min:3', 'max:255'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'cantidad.gt' => 'La cantidad debe ser mayor que cero.',
            'fecha.before_or_equal' => 'La fecha de la entrada no puede ser futura.',
        ];
    }

    public function attributes(): array
    {
        return ['costo_unitario' => 'costo unitario', 'stock_contado' => 'stock contado'];
    }
}
