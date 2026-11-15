<?php

namespace App\Facturacion;

use App\Enums\TipoDescuento;

/** Resultado de calcular una cuenta (RF29, RF30). Todos los valores están en pesos. */
final readonly class ResumenFactura
{
    public function __construct(
        public float $subtotal,
        public float $descuentoPromociones,
        public TipoDescuento $descuentoTipo,
        public float $descuentoValor,
        public float $descuento,
        public string $impuestoNombre,
        public float $impuestoPorcentaje,
        public bool $impuestoIncluido,
        public float $baseGravable,
        public float $impuesto,
        public float $propina,
        public float $total,
    ) {}

    /** @return array<string, mixed> columnas de la tabla facturas */
    public function aArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'descuento_promociones' => $this->descuentoPromociones,
            'descuento_tipo' => $this->descuentoTipo->value,
            'descuento_valor' => $this->descuentoValor,
            'descuento' => $this->descuento,
            'impuesto_nombre' => $this->impuestoNombre,
            'impuesto_porcentaje' => $this->impuestoPorcentaje,
            'impuesto_incluido' => $this->impuestoIncluido,
            'base_gravable' => $this->baseGravable,
            'impuesto' => $this->impuesto,
            'propina' => $this->propina,
            'total' => $this->total,
        ];
    }
}
