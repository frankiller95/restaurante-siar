<?php

namespace App\Facturacion\Descuentos;

/** Descuento como porcentaje de la base (ej. 10 = 10 %). */
class DescuentoPorcentaje implements EstrategiaDescuento
{
    public function calcular(float $base, float $valor): float
    {
        return round(max(0, $base) * $valor / 100, 2);
    }

    public function validar(float $base, float $valor): ?string
    {
        if ($valor <= 0 || $valor > 100) {
            return 'El porcentaje de descuento debe estar entre 0,01 y 100.';
        }

        return null;
    }
}
