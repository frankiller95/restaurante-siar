<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CambiarContrasenaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string'],
            'contrasena' => [...ReglasContrasena::nueva(), 'different:contrasena_actual'],
        ];
    }
}
