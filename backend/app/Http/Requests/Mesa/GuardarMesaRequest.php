<?php

namespace App\Http\Requests\Mesa;

use Illuminate\Foundation\Http\FormRequest;

/** RF12 / RF13. El número único lo verifica MesaService. */
class GuardarMesaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'numero' => ['required', 'integer', 'min:1', 'max:999'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:30'],
            'ubicacion' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function attributes(): array
    {
        return ['numero' => 'número de mesa', 'capacidad' => 'capacidad', 'ubicacion' => 'ubicación'];
    }
}
