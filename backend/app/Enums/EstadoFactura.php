<?php

namespace App\Enums;

/**
 * Estados de una factura (RF28, RF31).
 *
 * pendiente → pagada (cuando los pagos cubren el total)
 * pendiente → anulada (solo si todavía no tiene pagos)
 */
enum EstadoFactura: string
{
    case PENDIENTE = 'pendiente';
    case PAGADA = 'pagada';
    case ANULADA = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente de pago',
            self::PAGADA => 'Pagada',
            self::ANULADA => 'Anulada',
        };
    }

    /** Vigente = cuenta como la factura del pedido (no está anulada). */
    public function esVigente(): bool
    {
        return $this !== self::ANULADA;
    }

    /** @return list<string> */
    public static function valoresVigentes(): array
    {
        return [self::PENDIENTE->value, self::PAGADA->value];
    }
}
