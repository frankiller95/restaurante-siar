<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;

class GuardarCategoriaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }
}
