<?php

namespace App\Estados\Mesa;

use App\Enums\EstadoMesa;
use App\Models\Mesa;

/** Reservada: se ocupa cuando llega el cliente o se libera si la reserva se cae. */
class MesaReservada extends EstadoBase
{
    public function ocupar(Mesa $mesa): EstadoMesa
    {
        return EstadoMesa::OCUPADA;
    }

    public function liberar(Mesa $mesa): EstadoMesa
    {
        return EstadoMesa::LIBRE;
    }
}
