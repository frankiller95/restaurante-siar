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
            'codigo_seguimiento' => $this->codigo_seguimiento,
            'tipo_pedido' => $this->tipo_pedido ?? 'mesa',
            'cliente_nombre' => $this->cliente_nombre,
            'cliente_telefono' => $this->cliente_telefono,
            'tiempo_estimado_minutos' => $this->tiempo_estimado_minutos,
            'estimado_listo_en' => $this->estimado_listo_en?->toIso8601String(),
            'propina' => (float) ($this->propina ?? 0),
            'descuento' => (float) ($this->descuento ?? 0),
            'estado_pago' => $this->estado_pago,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'enviado_cocina' => $this->fueEnviadoACocina(),
            'editable' => $this->esEditable(),
            'acciones' => array_map(fn ($estado) => $estado->value, $this->estado->transicionesPermitidas()),
            'observaciones' => $this->observaciones,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'mesa' => $this->whenLoaded('mesa', fn () => [
                'id_mesa' => $this->mesa?->id_mesa,
                'numero' => $this->mesa?->numero,
                'ubicacion' => $this->mesa?->ubicacion,
            ]),
            'mesero' => $this->whenLoaded('usuario', fn () => $this->usuario?->nombres),
            // Módulo 6: la factura vigente del pedido (null si todavía no se factura).
            'factura' => $this->whenLoaded('facturaVigente', fn () => $this->facturaVigente ? [
                'id_factura' => $this->facturaVigente->id_factura,
                'numero' => $this->facturaVigente->numero,
                'estado' => $this->facturaVigente->estado->value,
                'estado_etiqueta' => $this->facturaVigente->estado->etiqueta(),
                'total' => (float) $this->facturaVigente->total,
            ] : null),
            'detalles' => $this->whenLoaded('detalles', fn () => $this->detalles->map(fn (DetallePedido $detalle) => [
                'id_detalle' => $detalle->getKey(),
                'id_producto' => $detalle->id_producto,
                'producto' => $detalle->nombre_producto ?: $detalle->producto?->nombre,
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => $detalle->subtotal() - (float) ($detalle->descuento ?? 0),
                'adiciones' => $detalle->adiciones->map(fn ($a) => [
                    'nombre' => $a->nombre,
                    'precio' => (float) $a->precio,
                ])->values(),
                'observacion' => $detalle->observacion,
            ])->values()),
            'cantidad_productos' => $this->whenLoaded('detalles', fn () => $this->detalles->sum('cantidad')),
            'total' => $this->whenLoaded('detalles', fn () => $this->total()),
            'mensajes' => $this->whenLoaded('mensajes', fn () => $this->mensajes->map(fn ($mensaje) => [
                'id_mensaje' => $mensaje->id_mensaje,
                'autor' => $mensaje->autor,
                'mensaje' => $mensaje->mensaje,
                'visibilidad' => $mensaje->visibilidad,
                'estado_publicacion' => $mensaje->estado_publicacion,
                'created_at' => $mensaje->created_at?->toIso8601String(),
            ])->values()),
            'fecha_creacion' => $this->fecha_creacion?->toIso8601String(),
            'fecha_envio_cocina' => $this->fecha_envio_cocina?->toIso8601String(),
            'fecha_inicio_preparacion' => $this->fecha_inicio_preparacion?->toIso8601String(),
            'fecha_listo' => $this->fecha_listo?->toIso8601String(),
            'fecha_entrega' => $this->fecha_entrega?->toIso8601String(),
            'fecha_cancelacion' => $this->fecha_cancelacion?->toIso8601String(),
        ];
    }
}
