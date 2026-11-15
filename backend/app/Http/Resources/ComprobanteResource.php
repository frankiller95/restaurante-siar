<?php

namespace App\Http\Resources;

use App\Models\Factura;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RF32: lo que lleva el comprobante impreso: datos del restaurante más la
 * factura completa. El frontend lo arma como ticket y lo imprime.
 *
 * @mixin Factura
 */
class ComprobanteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pagada = $this->estado->value === 'pagada';

        return [
            'titulo' => $pagada ? 'Comprobante de pago' : 'Factura pendiente de pago',
            'emisor' => config('siar.facturacion.emisor'),
            'factura' => (new FacturaResource($this->resource))->resolve($request),
            'cambio_total' => round($this->pagos->sum(fn ($pago) => (float) $pago->cambio), 2),
        ];
    }
}
