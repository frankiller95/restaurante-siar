<?php

namespace App\Enums;

use App\Estados\Mesa\ComportamientoMesa;
use App\Estados\Mesa\MesaEnMantenimiento;
use App\Estados\Mesa\MesaLibre;
use App\Estados\Mesa\MesaOcupada;
use App\Estados\Mesa\MesaReservada;

/** Estados de una mesa. Cada uno tiene su clase de comportamiento (patrón State). */
enum EstadoMesa: string
{
    case LIBRE = 'libre';
    case OCUPADA = 'ocupada';
    case RESERVADA = 'reservada';
    case MANTENIMIENTO = 'mantenimiento';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LIBRE => 'Libre',
            self::OCUPADA => 'Ocupada',
            self::RESERVADA => 'Reservada',
            self::MANTENIMIENTO => 'En mantenimiento',
        };
    }

    /** Objeto que sabe qué transiciones permite este estado. */
    public function comportamiento(): ComportamientoMesa
    {
        return match ($this) {
            self::LIBRE => new MesaLibre,
            self::OCUPADA => new MesaOcupada,
            self::RESERVADA => new MesaReservada,
            self::MANTENIMIENTO => new MesaEnMantenimiento,
        };
    }
}
