<?php

namespace App\Facturacion\Descuentos;

/**
 * Patrón Strategy (RF30): cada tipo de descuento sabe cuánto descontar y
 * qué valores acepta. La calculadora de factura no conoce los tipos.
 */
interface EstrategiaDescuento
{
    /**
     * Descuento en pesos para ese valor (porcentaje o monto). Nunca supera
     * la base sobre la que se aplica.
     */
    public function calcular(float $base, float $valor): float;

    /** Mensaje de error si el valor no es válido para este tipo; null si lo es. */
    public function validar(float $base, float $valor): ?string;
}
