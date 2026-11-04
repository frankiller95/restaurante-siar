<?php

namespace App\Estados\Mesa;

use App\Enums\EstadoMesa;
use App\Models\Mesa;

/** En mantenimiento: solo se puede habilitar de nuevo. */
class MesaEnMantenimiento extends EstadoBase
{
    public function habilitar(Mesa $mesa): EstadoMesa
    {
        return EstadoMesa::LIBRE;
    }
}
