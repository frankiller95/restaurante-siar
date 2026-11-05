<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF18. */
class AgregarProductoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id_producto' => ['required', 'integer', Rule::exists('productos', 'id_producto')],
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['id_producto' => 'producto', 'observacion' => 'observación'];
    }
}
