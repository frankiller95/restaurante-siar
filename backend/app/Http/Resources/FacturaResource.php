<?php

namespace App\Http\Resources;

use App\Models\DetalleFactura;
use App\Models\Factura;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Factura */
class FacturaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pagado = $this->totalPagado();

        return [
            'id_factura' => $this->id_factura,
            'numero' => $this->numero,
            'estado' => $this->estado->value,
            'estado_etiqueta' => $this->estado->etiqueta(),
            'id_pedido' => $this->id_pedido,
            'pedido' => $this->whenLoaded('pedido', fn () => [
                'id_pedido' => $this->pedido->id_pedido,
                'tipo_pedido' => $this->pedido->tipo_pedido ?? 'mesa',
                'estado' => $this->pedido->estado->value,
                'mesa' => $this->pedido->mesa ? [
                    'id_mesa' => $this->pedido->mesa->id_mesa,
                    'numero' => $this->pedido->mesa->numero,
                    'estado' => $this->pedido->mesa->estado->value,
                ] : null,
            ]),
            'cajero' => $this->whenLoaded('usuario', fn () => $this->usuario?->nombres),
            'cliente_nombre' => $this->cliente_nombre,
            'cliente_documento' => $this->cliente_documento,

            'subtotal' => (float) $this->subtotal,
            'descuento_promociones' => (float) $this->descuento_promociones,
            'descuento_tipo' => $this->descuento_tipo->value,
            'descuento_valor' => (float) $this->descuento_valor,
            'descuento' => (float) $this->descuento,
            'motivo_descuento' => $this->motivo_descuento,
            'impuesto_nombre' => $this->impuesto_nombre,
            'impuesto_porcentaje' => (float) $this->impuesto_porcentaje,
            'impuesto_incluido' => $this->impuesto_incluido,
            'base_gravable' => (float) $this->base_gravable,
            'impuesto' => (float) $this->impuesto,
            'propina' => (float) $this->propina,
            'total' => (float) $this->total,
            'total_pagado' => $pagado,
            'saldo' => $this->saldo(),
            'motivo_anulacion' => $this->motivo_anulacion,

            // Lo que se puede hacer ahora mismo (el frontend muestra solo esos botones).
            'puede_pagar' => $this->estaPendiente(),
            'puede_aplicar_descuento' => $this->estaPendiente() && $pagado <= 0,
            'puede_anular' => $this->estaPendiente() && $pagado <= 0,

            'detalles' => $this->whenLoaded('detalles', fn () => $this->detalles->map(fn (DetalleFactura $d) => [
                'id_detalle_factura' => $d->id_detalle_factura,
                'descripcion' => $d->descripcion,
                'adiciones' => $d->adiciones,
                'cantidad' => $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'descuento' => (float) $d->descuento,
                'subtotal' => (float) $d->subtotal,
            ])->values()),
            'pagos' => $this->whenLoaded('pagos', fn () => $this->pagos->map(fn (Pago $p) => [
                'id_pago' => $p->id_pago,
                'metodo' => $p->metodo->value,
                'metodo_etiqueta' => $p->metodo->etiqueta(),
                'monto' => (float) $p->monto,
                'monto_recibido' => $p->monto_recibido !== null ? (float) $p->monto_recibido : null,
                'cambio' => (float) $p->cambio,
                'referencia' => $p->referencia,
                'cajero' => $p->relationLoaded('usuario') ? $p->usuario?->nombres : null,
                'fecha' => $p->fecha?->toIso8601String(),
            ])->values()),

            'fecha_emision' => $this->fecha_emision?->toIso8601String(),
            'fecha_pago' => $this->fecha_pago?->toIso8601String(),
            'fecha_anulacion' => $this->fecha_anulacion?->toIso8601String(),
            'comprobante_emitido_en' => $this->comprobante_emitido_en?->toIso8601String(),
        ];
    }
}
