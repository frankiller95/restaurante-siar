<?php

namespace App\Http\Resources;

use App\Models\Insumo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Insumo */
class InsumoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_insumo' => $this->id_insumo,
            'nombre' => $this->nombre,
            'unidad_medida' => $this->unidad_medida->value,
            'unidad' => $this->unidad_medida->abreviatura(),
            'stock_actual' => (float) $this->stock_actual,
            'stock_minimo' => (float) $this->stock_minimo,
            'costo_unitario' => (float) $this->costo_unitario,
            'valor_inventario' => round(max(0, (float) $this->stock_actual) * (float) $this->costo_unitario, 2),
            'bajo_minimo' => $this->activo && $this->estaBajoMinimo(),
            'activo' => $this->activo,
            'recetas_count' => $this->whenCounted('recetas'),
            'fecha_modificacion' => $this->fecha_modificacion?->toIso8601String(),
        ];
    }
}
