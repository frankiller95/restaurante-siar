<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RestablecerContrasenaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string'],
            'contrasena' => ReglasContrasena::nueva(),
        ];
    }
}
