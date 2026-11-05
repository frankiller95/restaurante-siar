<?php

namespace App\Http\Requests\Inventario;

use App\Enums\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF23 / RF24. El stock inicial solo se acepta al crear. */
class GuardarInsumoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:100'],
            'unidad_medida' => ['required', Rule::enum(UnidadMedida::class)],
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:999999'],
            'costo_unitario' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'stock_inicial' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return ['stock_inicial.prohibited' => 'El stock no se edita aquí: registra una entrada, una merma o un ajuste.'];
    }

    public function attributes(): array
    {
        return [
            'unidad_medida' => 'unidad de medida',
            'stock_minimo' => 'stock mínimo',
            'costo_unitario' => 'costo unitario',
            'stock_inicial' => 'stock inicial',
        ];
    }
}
