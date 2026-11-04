<?php

namespace App\Http\Requests\Mesa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF15. Las reglas de horario y capacidad las verifica ReservaService. */
class GuardarReservaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'cantidad_personas' => ['required', 'integer', 'min:1', 'max:30'],
            'id_mesa' => ['required', 'integer', Rule::exists('mesas', 'id_mesa')],
            'cliente_nombre' => ['required', 'string', 'min:3', 'max:120'],
            'cliente_telefono' => ['required', 'string', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_telefono.regex' => 'El teléfono debe tener entre 7 y 15 dígitos.',
            'fecha.date_format' => 'La fecha no es válida.',
            'hora.date_format' => 'La hora no es válida (usa HH:MM).',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_mesa' => 'mesa',
            'cantidad_personas' => 'cantidad de personas',
            'cliente_nombre' => 'nombre del cliente',
            'cliente_telefono' => 'teléfono del cliente',
        ];
    }
}
