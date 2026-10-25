<?php

namespace App\Http\Resources;

use App\Models\BitacoraAuditoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BitacoraAuditoria */
class BitacoraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_bitacora' => $this->id_bitacora,
            'tipo_evento' => $this->tipo_evento,
            'descripcion' => $this->descripcion,
            'fecha_hora' => $this->fecha_hora?->toIso8601String(),
            'ip_origen' => $this->ip_origen,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? [
                'id_usuario' => $this->usuario->id_usuario,
                'nombres' => $this->usuario->nombres,
                'correo' => $this->usuario->correo,
            ] : null),
        ];
    }
}
