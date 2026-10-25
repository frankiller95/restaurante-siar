<?php

namespace App\Http\Requests\Rol;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarPermisosRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'permisos' => ['present', 'array'],
            'permisos.*' => ['integer', 'distinct', Rule::exists('permisos', 'id_permiso')],
        ];
    }
}
