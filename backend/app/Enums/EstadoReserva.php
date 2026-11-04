<?php

namespace App\Enums;

enum EstadoReserva: string
{
    case PENDIENTE = 'pendiente';
    case CUMPLIDA = 'cumplida';
    case CANCELADA = 'cancelada';
    case NO_ASISTIO = 'no_asistio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::CUMPLIDA => 'Cumplida',
            self::CANCELADA => 'Cancelada',
            self::NO_ASISTIO => 'No asistió',
        };
    }
}
