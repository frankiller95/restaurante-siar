<?php

namespace App\Http\Resources;

use App\Models\MovimientoInventario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MovimientoInventario */
class MovimientoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_movimiento' => $this->id_movimiento,
            'tipo' => $this->tipo->value,
            'tipo_etiqueta' => $this->tipo->etiqueta(),
            'cantidad' => (float) $this->cantidad,
            'stock_resultante' => (float) $this->stock_resultante,
            'costo_unitario' => $this->costo_unitario !== null ? (float) $this->costo_unitario : null,
            'costo_total' => $this->costo_unitario !== null ? $this->calcularCostoTotal() : null,
            'proveedor' => $this->proveedor,
            'motivo' => $this->motivo,
            'id_pedido' => $this->id_pedido,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->nombres),
            'fecha' => $this->fecha?->toIso8601String(),
        ];
    }
}
