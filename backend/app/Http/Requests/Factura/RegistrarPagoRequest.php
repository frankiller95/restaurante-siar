<?php

namespace App\Http\Requests\Factura;

use App\Enums\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF31: un pago de la factura. El efectivo admite "monto_recibido" (para
 * calcular el cambio); tarjeta y transferencia llevan número de aprobación.
 */
class RegistrarPagoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'metodo' => ['required', Rule::enum(MetodoPago::class)],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'monto_recibido' => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'referencia' => [
                Rule::requiredIf(fn () => in_array($this->input('metodo'), [MetodoPago::TARJETA->value, MetodoPago::TRANSFERENCIA->value], true)),
                'nullable', 'string', 'max:60',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'referencia.required' => 'Escribe el número de aprobación o de la transferencia.',
        ];
    }

    public function attributes(): array
    {
        return [
            'metodo' => 'método de pago',
            'monto_recibido' => 'efectivo recibido',
        ];
    }
}
