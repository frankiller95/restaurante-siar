<?php

namespace App\Facturacion\Descuentos;

/** Descuento de un valor fijo en pesos. */
class DescuentoValorFijo implements EstrategiaDescuento
{
    public function calcular(float $base, float $valor): float
    {
        return round(min(max(0, $base), $valor), 2);
    }

    public function validar(float $base, float $valor): ?string
    {
        if ($valor <= 0) {
            return 'El valor del descuento debe ser mayor que cero.';
        }
        if ($valor > $base) {
            return 'El descuento no puede ser mayor que el valor de la cuenta.';
        }

        return null;
    }
}
