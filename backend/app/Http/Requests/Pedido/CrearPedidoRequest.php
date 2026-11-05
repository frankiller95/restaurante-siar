<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF17 (+RF18): mesa y, opcionalmente, los productos iniciales. */
class CrearPedidoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id_mesa' => ['required', 'integer', Rule::exists('mesas', 'id_mesa')],
            'observaciones' => ['nullable', 'string', 'max:255'],
            'productos' => ['sometimes', 'array', 'max:50'],
            'productos.*.id_producto' => ['required', 'integer', Rule::exists('productos', 'id_producto')],
            'productos.*.cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'productos.*.observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_mesa' => 'mesa',
            'productos.*.id_producto' => 'producto',
            'productos.*.cantidad' => 'cantidad',
            'productos.*.observacion' => 'observación',
        ];
    }
}
