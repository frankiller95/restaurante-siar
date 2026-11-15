<?php

namespace App\Enums;

/** Formas de pago que acepta la caja (RF31). */
enum MetodoPago: string
{
    case EFECTIVO = 'efectivo';
    case TARJETA = 'tarjeta';
    case TRANSFERENCIA = 'transferencia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EFECTIVO => 'Efectivo',
            self::TARJETA => 'Tarjeta',
            self::TRANSFERENCIA => 'Transferencia',
        };
    }

    /** Tarjeta y transferencia se concilian con un número de aprobación. */
    public function requiereReferencia(): bool
    {
        return $this !== self::EFECTIVO;
    }
}
