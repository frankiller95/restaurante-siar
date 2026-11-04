<?php

namespace App\Http\Resources;

use App\Models\Producto;
use App\Services\ProductoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Producto */
class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_producto' => $this->id_producto,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'precio' => (float) $this->precio,
            'imagen_url' => ProductoService::urlImagen($this->imagen_url),
            'disponible' => $this->disponible,
            'activo' => $this->activo,
            'id_categoria' => $this->id_categoria,
            'categoria' => $this->whenLoaded('categoria', fn () => [
                'id_categoria' => $this->categoria->id_categoria,
                'nombre' => $this->categoria->nombre,
                'activa' => $this->categoria->activa,
            ]),
            'fecha_creacion' => $this->fecha_creacion?->toIso8601String(),
            'fecha_modificacion' => $this->fecha_modificacion?->toIso8601String(),
            'fecha_baja' => $this->fecha_baja?->toIso8601String(),
        ];
    }
}
