<?php

namespace App\Enums;

/** Tipos de movimiento del kárdex (RF25, RF26, RF39). */
enum TipoMovimiento: string
{
    case ENTRADA = 'entrada';
    case CONSUMO_PEDIDO = 'consumo_pedido';
    case REINTEGRO_PEDIDO = 'reintegro_pedido';
    case MERMA = 'merma';
    case AJUSTE = 'ajuste';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ENTRADA => 'Entrada',
            self::CONSUMO_PEDIDO => 'Consumo por pedido',
            self::REINTEGRO_PEDIDO => 'Reintegro por pedido cancelado',
            self::MERMA => 'Merma',
            self::AJUSTE => 'Ajuste',
        };
    }
}
