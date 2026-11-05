<?php

namespace App\Http\Resources;

use App\Models\DetallePedido;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pedido */
class PedidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_pedido' => $this->id_pedido,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'enviado_cocina' => $this->fueEnviadoACocina(),
            'editable' => $this->esEditable(),
            'acciones' => array_map(fn ($estado) => $estado->value, $this->estado->transicionesPermitidas()),
            'observaciones' => $this->observaciones,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'mesa' => $this->whenLoaded('mesa', fn () => [
                'id_mesa' => $this->mesa->id_mesa,
                'numero' => $this->mesa->numero,
                'ubicacion' => $this->mesa->ubicacion,
            ]),
            'mesero' => $this->whenLoaded('usuario', fn () => $this->usuario?->nombres),
            'detalles' => $this->whenLoaded('detalles', fn () => $this->detalles->map(fn (DetallePedido $detalle) => [
                'id_detalle' => $detalle->id_detalle,
                'id_producto' => $detalle->id_producto,
                'producto' => $detalle->producto?->nombre,
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => $detalle->subtotal(),
                'observacion' => $detalle->observacion,
            ])->values()),
            'cantidad_productos' => $this->whenLoaded('detalles', fn () => $this->detalles->sum('cantidad')),
            'total' => $this->whenLoaded('detalles', fn () => $this->total()),
            'fecha_creacion' => $this->fecha_creacion?->toIso8601String(),
            'fecha_envio_cocina' => $this->fecha_envio_cocina?->toIso8601String(),
            'fecha_inicio_preparacion' => $this->fecha_inicio_preparacion?->toIso8601String(),
            'fecha_listo' => $this->fecha_listo?->toIso8601String(),
            'fecha_entrega' => $this->fecha_entrega?->toIso8601String(),
            'fecha_cancelacion' => $this->fecha_cancelacion?->toIso8601String(),
        ];
    }
}
