<?php

namespace App\Enums;

enum EstadoUsuario: string
{
    case ACTIVO = 'activo';
    case INACTIVO = 'inactivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACTIVO => 'Activo',
            self::INACTIVO => 'Inactivo',
        };
    }
}
