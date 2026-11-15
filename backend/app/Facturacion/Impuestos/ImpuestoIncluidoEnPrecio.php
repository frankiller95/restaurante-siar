<?php

namespace App\Facturacion\Impuestos;

/**
 * Los precios del menú ya traen el impuesto: se "extrae" del valor de la cuenta.
 * El total no cambia; la factura solo discrimina cuánto es impuesto.
 */
class ImpuestoIncluidoEnPrecio implements EstrategiaImpuesto
{
    public function calcular(float $neto, float $porcentaje): array
    {
        $neto = round(max(0, $neto), 2);
        $impuesto = round($neto * $porcentaje / (100 + $porcentaje), 2);

        return [
            'base_gravable' => round($neto - $impuesto, 2),
            'impuesto' => $impuesto,
            'total_productos' => $neto,
        ];
    }

    public function incluidoEnPrecio(): bool
    {
        return true;
    }
}
