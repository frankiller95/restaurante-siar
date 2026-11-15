<?php

namespace App\Facturacion\Impuestos;

/** Los precios del menú no traen impuesto: se suma sobre el valor de la cuenta. */
class ImpuestoAdicional implements EstrategiaImpuesto
{
    public function calcular(float $neto, float $porcentaje): array
    {
        $neto = round(max(0, $neto), 2);
        $impuesto = round($neto * $porcentaje / 100, 2);

        return [
            'base_gravable' => $neto,
            'impuesto' => $impuesto,
            'total_productos' => round($neto + $impuesto, 2),
        ];
    }

    public function incluidoEnPrecio(): bool
    {
        return false;
    }
}
