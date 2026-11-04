<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarCategoriaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id_categoria' => ['required', 'integer', Rule::exists('categorias', 'id_categoria')],
        ];
    }

    public function attributes(): array
    {
        return ['id_categoria' => 'categoría'];
    }
}
