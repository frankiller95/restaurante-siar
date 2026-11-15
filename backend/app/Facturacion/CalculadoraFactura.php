<?php

namespace App\Facturacion;

use App\Enums\TipoDescuento;
use App\Facturacion\Impuestos\EstrategiaImpuesto;
use App\Facturacion\Impuestos\ImpuestoAdicional;
use App\Facturacion\Impuestos\ImpuestoIncluidoEnPrecio;
use App\Models\DetallePedido;
use App\Models\Pedido;
use Illuminate\Validation\ValidationException;

/**
 * RF29 (impuestos) y RF30 (descuentos): calcula los valores de una factura a
 * partir de un pedido. No guarda nada; el servicio de facturación usa el
 * resultado para crear la factura o para mostrar una vista previa.
 *
 * Orden del cálculo:
 *   subtotal (productos) − descuentos de promociones − descuento del cajero
 *   → impuesto (según la estrategia configurada) → + propina → total.
 */
class CalculadoraFactura
{
    public function __construct(
        private readonly string $impuestoNombre,
        private readonly float $impuestoPorcentaje,
        private readonly EstrategiaImpuesto $impuesto,
    ) {}

    /** Arma la calculadora con la configuración de config/siar.php. */
    public static function desdeConfiguracion(): self
    {
        $config = config('siar.facturacion.impuesto');

        return new self(
            (string) $config['nombre'],
            (float) $config['porcentaje'],
            $config['incluido_en_precio'] ? new ImpuestoIncluidoEnPrecio : new ImpuestoAdicional,
        );
    }

    /** @throws ValidationException si el descuento no es válido para la cuenta */
    public function calcular(Pedido $pedido, TipoDescuento $tipo = TipoDescuento::NINGUNO, float $valorDescuento = 0): ResumenFactura
    {
        $pedido->loadMissing('detalles');

        $subtotal = round($pedido->detalles->sum(fn (DetallePedido $d) => $d->subtotal()), 2);
        $promociones = round($pedido->detalles->sum(fn (DetallePedido $d) => (float) ($d->descuento ?? 0)), 2);
        $neto = round(max(0, $subtotal - $promociones), 2);

        $estrategiaDescuento = $tipo->estrategia();
        $valorDescuento = $tipo === TipoDescuento::NINGUNO ? 0.0 : round($valorDescuento, 2);

        if ($error = $estrategiaDescuento->validar($neto, $valorDescuento)) {
            throw ValidationException::withMessages(['descuento_valor' => $error]);
        }

        $descuento = $estrategiaDescuento->calcular($neto, $valorDescuento);
        $impuesto = $this->impuesto->calcular(round($neto - $descuento, 2), $this->impuestoPorcentaje);
        $propina = round((float) ($pedido->propina ?? 0), 2);

        return new ResumenFactura(
            subtotal: $subtotal,
            descuentoPromociones: $promociones,
            descuentoTipo: $tipo,
            descuentoValor: $valorDescuento,
            descuento: $descuento,
            impuestoNombre: $this->impuestoNombre,
            impuestoPorcentaje: $this->impuestoPorcentaje,
            impuestoIncluido: $this->impuesto->incluidoEnPrecio(),
            baseGravable: $impuesto['base_gravable'],
            impuesto: $impuesto['impuesto'],
            propina: $propina,
            total: round($impuesto['total_productos'] + $propina, 2),
        );
    }
}
