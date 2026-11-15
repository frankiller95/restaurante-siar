<?php

namespace App\Enums;

use App\Facturacion\Descuentos\DescuentoPorcentaje;
use App\Facturacion\Descuentos\DescuentoValorFijo;
use App\Facturacion\Descuentos\EstrategiaDescuento;
use App\Facturacion\Descuentos\SinDescuento;

/**
 * Tipos de descuento que puede aplicar el cajero (RF30). Cada tipo tiene su
 * estrategia de cálculo (patrón Strategy, App\Facturacion\Descuentos).
 */
enum TipoDescuento: string
{
    case NINGUNO = 'ninguno';
    case PORCENTAJE = 'porcentaje';
    case VALOR = 'valor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NINGUNO => 'Sin descuento',
            self::PORCENTAJE => 'Porcentaje',
            self::VALOR => 'Valor fijo',
        };
    }

    public function estrategia(): EstrategiaDescuento
    {
        return match ($this) {
            self::NINGUNO => new SinDescuento,
            self::PORCENTAJE => new DescuentoPorcentaje,
            self::VALOR => new DescuentoValorFijo,
        };
    }
}
