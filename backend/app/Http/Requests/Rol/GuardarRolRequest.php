<?php

namespace App\Http\Requests\Rol;

use Illuminate\Foundation\Http\FormRequest;

class GuardarRolRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre_rol' => ['required', 'string', 'min:3', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['nombre_rol' => 'nombre del rol'];
    }
}
