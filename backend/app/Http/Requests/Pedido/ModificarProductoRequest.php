<?php

namespace App\Http\Requests\Pedido;

use Illuminate\Foundation\Http\FormRequest;

/** RF19. */
class ModificarProductoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['observacion' => 'observación'];
    }
}
