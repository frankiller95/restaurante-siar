<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class CambiarDisponibilidadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'disponible' => ['required', 'boolean'],
        ];
    }
}
