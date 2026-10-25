<?php

namespace App\Http\Resources;

use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permiso */
class PermisoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_permiso' => $this->id_permiso,
            'codigo_permiso' => $this->codigo_permiso,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ];
    }
}
