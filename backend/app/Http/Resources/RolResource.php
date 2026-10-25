<?php

namespace App\Http\Resources;

use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rol */
class RolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_rol' => $this->id_rol,
            'nombre_rol' => $this->nombre_rol,
            'descripcion' => $this->descripcion,
            'es_predefinido' => $this->es_predefinido,
            'usuarios_count' => $this->whenCounted('usuarios'),
            'permisos' => PermisoResource::collection($this->whenLoaded('permisos')),
        ];
    }
}
