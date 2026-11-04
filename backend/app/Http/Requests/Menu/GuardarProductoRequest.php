<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU01 paso 4 / CU02 paso 4: campos obligatorios y precio válido (no negativo).
 * Se envía como multipart/form-data porque puede incluir la imagen.
 * Para editar con imagen, el frontend usa POST con "_method=PUT".
 */
class GuardarProductoRequest extends FormRequest
{
    public function rules(): array
    {
        $imagenes = config('siar.imagenes');

        return [
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'precio' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'id_categoria' => ['required', 'integer', Rule::exists('categorias', 'id_categoria')],
            'imagen' => ['nullable', 'image', 'mimes:'.implode(',', $imagenes['formatos']), 'max:'.$imagenes['max_kb']],
            'quitar_imagen' => ['sometimes', 'boolean'],
            'confirmar_duplicado' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'precio.min' => 'El precio no puede ser negativo.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
        ];
    }

    public function attributes(): array
    {
        return ['id_categoria' => 'categoría', 'precio' => 'precio', 'imagen' => 'imagen'];
    }
}
