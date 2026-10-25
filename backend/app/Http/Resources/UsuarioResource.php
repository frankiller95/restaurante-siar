<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO de salida: nunca expone la contraseña cifrada ni los tokens.
 *
 * @mixin Usuario
 */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_usuario' => $this->id_usuario,
            'nombres' => $this->nombres,
            'documento' => $this->documento,
            'correo' => $this->correo,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'id_rol' => $this->id_rol,
            'rol' => new RolResumenResource($this->whenLoaded('rol')),
            'fecha_creacion' => $this->fecha_creacion?->toIso8601String(),
            'fecha_actualizacion' => $this->fecha_actualizacion?->toIso8601String(),
        ];
    }
}
