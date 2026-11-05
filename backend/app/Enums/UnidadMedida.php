<?php

namespace App\Enums;

enum UnidadMedida: string
{
    case KILOGRAMO = 'kilogramo';
    case GRAMO = 'gramo';
    case LITRO = 'litro';
    case MILILITRO = 'mililitro';
    case UNIDAD = 'unidad';

    public function abreviatura(): string
    {
        return match ($this) {
            self::KILOGRAMO => 'kg',
            self::GRAMO => 'g',
            self::LITRO => 'L',
            self::MILILITRO => 'ml',
            self::UNIDAD => 'und',
        };
    }
}
