<?php

namespace App\Estados\Mesa;

use App\Enums\EstadoMesa;
use App\Models\Mesa;

/**
 * Patrón State: cada estado de la mesa implementa esta interfaz y decide si
 * permite la acción. Si la permite, devuelve el estado siguiente; si no,
 * lanza TransicionMesaException.
 */
interface ComportamientoMesa
{
    public function ocupar(Mesa $mesa): EstadoMesa;

    public function reservar(Mesa $mesa): EstadoMesa;

    public function liberar(Mesa $mesa): EstadoMesa;

    public function enviarAMantenimiento(Mesa $mesa): EstadoMesa;

    public function habilitar(Mesa $mesa): EstadoMesa;
}
