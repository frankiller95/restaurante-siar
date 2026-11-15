<?php

namespace App\Http\Requests\Factura;

use App\Enums\TipoDescuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF30: descuento de una factura pendiente (tipo "ninguno" lo quita). */
class AplicarDescuentoRequest extends FormRequest
{
    public function rules(): array
    {
        $conDescuento = fn () => in_array($this->input('descuento_tipo'), [TipoDescuento::PORCENTAJE->value, TipoDescuento::VALOR->value], true);

        return [
            'descuento_tipo' => ['required', Rule::enum(TipoDescuento::class)],
            'descuento_valor' => [Rule::requiredIf($conDescuento), 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'motivo_descuento' => [Rule::requiredIf($conDescuento), 'nullable', 'string', 'min:3', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'descuento_tipo' => 'tipo de descuento',
            'descuento_valor' => 'valor del descuento',
            'motivo_descuento' => 'motivo del descuento',
        ];
    }
}
