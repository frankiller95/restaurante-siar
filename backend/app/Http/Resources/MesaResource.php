<?php

namespace App\Http\Resources;

use App\Models\Mesa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Mesa */
class MesaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_mesa' => $this->id_mesa,
            'numero' => $this->numero,
            'capacidad' => $this->capacidad,
            'ubicacion' => $this->ubicacion,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'activo' => $this->activo,
            // Lo que permite el estado actual (patrón State).
            'acciones' => $this->activo ? $this->accionesPermitidas() : [],
            'proxima_reserva' => $this->whenLoaded('proximaReserva', fn () => $this->proximaReserva ? [
                'id_reserva' => $this->proximaReserva->id_reserva,
                'fecha' => $this->proximaReserva->fecha->format('Y-m-d'),
                'hora' => substr($this->proximaReserva->hora, 0, 5),
                'cliente_nombre' => $this->proximaReserva->cliente_nombre,
                'cantidad_personas' => $this->proximaReserva->cantidad_personas,
            ] : null),
        ];
    }
}
