<?php

namespace App\Facturacion\Descuentos;

class SinDescuento implements EstrategiaDescuento
{
    public function calcular(float $base, float $valor): float
    {
        return 0.0;
    }

    public function validar(float $base, float $valor): ?string
    {
        return null;
    }
}
