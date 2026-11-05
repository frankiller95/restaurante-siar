<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF38: la receta completa del producto (reemplaza la anterior). */
class GuardarRecetaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'insumos' => ['present', 'array', 'max:40'],
            'insumos.*.id_insumo' => ['required', 'integer', 'distinct', Rule::exists('insumos', 'id_insumo')],
            'insumos.*.cantidad_por_porcion' => ['required', 'numeric', 'gt:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'insumos.*.id_insumo.distinct' => 'Un insumo está repetido en la receta.',
            'insumos.*.cantidad_por_porcion.gt' => 'La cantidad por porción debe ser mayor que cero.',
        ];
    }

    public function attributes(): array
    {
        return ['insumos.*.id_insumo' => 'insumo', 'insumos.*.cantidad_por_porcion' => 'cantidad por porción'];
    }
}
