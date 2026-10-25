<?php

namespace App\Http\Requests\Usuario;

use App\Enums\EstadoUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU-01 paso 3 / CU-02 paso 4: datos obligatorios y con formato válido.
 * La unicidad de correo y documento la verifica UsuarioService.
 */
class GuardarUsuarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'min:3', 'max:120'],
            'documento' => ['required', 'string', 'regex:/^[0-9A-Za-z-]{5,20}$/'],
            'correo' => ['required', 'email', 'max:150'],
            'id_rol' => ['required', 'integer', Rule::exists('roles', 'id_rol')],
            'estado' => [$this->isMethod('POST') ? 'prohibited' : 'required', Rule::enum(EstadoUsuario::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'documento.regex' => 'El documento debe tener entre 5 y 20 caracteres (números, letras o guion).',
        ];
    }

    public function attributes(): array
    {
        return ['id_rol' => 'rol'];
    }
}
