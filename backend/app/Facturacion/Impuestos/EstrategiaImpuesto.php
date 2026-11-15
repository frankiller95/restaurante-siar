<?php

namespace App\Facturacion\Impuestos;

/**
 * Patrón Strategy (RF29): cómo se calcula el impuesto sobre el valor de la
 * cuenta (ya con descuentos). Hay dos formas según cómo se manejen los precios
 * del menú: con el impuesto ya incluido o sumado aparte.
 */
interface EstrategiaImpuesto
{
    /**
     * "total_productos" es lo que paga el cliente por los productos, con el
     * impuesto incluido.
     *
     * @param  float  $neto  valor de los productos después de descuentos
     * @param  float  $porcentaje  tarifa del impuesto (ej. 8 = 8 %)
     * @return array{base_gravable: float, impuesto: float, total_productos: float}
     */
    public function calcular(float $neto, float $porcentaje): array;

    public function incluidoEnPrecio(): bool;
}
