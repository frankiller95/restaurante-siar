<?php

namespace App\Estados\Mesa;

use App\Enums\EstadoMesa;
use App\Models\Mesa;

/** Libre: se puede ocupar, reservar o enviar a mantenimiento. */
class MesaLibre extends EstadoBase
{
    public function ocupar(Mesa $mesa): EstadoMesa
    {
        return EstadoMesa::OCUPADA;
    }

    public function reservar(Mesa $mesa): EstadoMesa
    {
        return EstadoMesa::RESERVADA;
    }

    public function enviarAMantenimiento(Mesa $mesa): EstadoMesa
    {
        return EstadoMesa::MANTENIMIENTO;
    }
}
