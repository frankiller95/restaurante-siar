<?php

namespace App\Estados\Mesa;

use App\Enums\EstadoMesa;
use App\Models\Mesa;

/** Ocupada: solo se puede liberar (RF16). */
class MesaOcupada extends EstadoBase
{
    public function liberar(Mesa $mesa): EstadoMesa
    {
        return EstadoMesa::LIBRE;
    }
}
