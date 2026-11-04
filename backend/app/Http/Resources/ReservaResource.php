<?php

namespace App\Http\Resources;

use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reserva */
class ReservaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_reserva' => $this->id_reserva,
            'fecha' => $this->fecha->format('Y-m-d'),
            'hora' => substr($this->hora, 0, 5),
            'inicio' => $this->inicio()->toIso8601String(),
            'cantidad_personas' => $this->cantidad_personas,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'cliente_nombre' => $this->cliente_nombre,
            'cliente_telefono' => $this->cliente_telefono,
            'observaciones' => $this->observaciones,
            'mesa' => $this->whenLoaded('mesa', fn () => [
                'id_mesa' => $this->mesa->id_mesa,
                'numero' => $this->mesa->numero,
                'ubicacion' => $this->mesa->ubicacion,
                'estado' => $this->mesa->estado->value,
            ]),
            'registrada_por' => $this->whenLoaded('usuario', fn () => $this->usuario?->nombres),
            'fecha_creacion' => $this->fecha_creacion?->toIso8601String(),
        ];
    }
}
