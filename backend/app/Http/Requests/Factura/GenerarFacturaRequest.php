<?php

namespace App\Http\Requests\Factura;

use App\Enums\TipoDescuento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF28 (+RF30): pedido a facturar, datos del cliente y descuento opcional.
 * También sirve para la vista previa de la cuenta.
 */
class GenerarFacturaRequest extends FormRequest
{
    public function rules(): array
    {
        $conDescuento = fn () => in_array($this->input('descuento_tipo'), [TipoDescuento::PORCENTAJE->value, TipoDescuento::VALOR->value], true);

        return [
            'id_pedido' => ['required', 'integer', Rule::exists('pedidos', 'id_pedido')],
            'cliente_nombre' => ['nullable', 'string', 'max:160'],
            'cliente_documento' => ['nullable', 'string', 'max:30'],
            'descuento_tipo' => ['nullable', Rule::enum(TipoDescuento::class)],
            'descuento_valor' => [Rule::requiredIf($conDescuento), 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'motivo_descuento' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_pedido' => 'pedido',
            'cliente_nombre' => 'nombre del cliente',
            'cliente_documento' => 'documento del cliente',
            'descuento_tipo' => 'tipo de descuento',
            'descuento_valor' => 'valor del descuento',
            'motivo_descuento' => 'motivo del descuento',
        ];
    }
}
