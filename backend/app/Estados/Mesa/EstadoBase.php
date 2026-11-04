<?php

namespace App\Estados\Mesa;

use App\Enums\EstadoMesa;
use App\Exceptions\TransicionMesaException;
use App\Models\Mesa;

/**
 * Por defecto ninguna transición está permitida. Cada estado concreto
 * sobrescribe solo las que acepta.
 */
abstract class EstadoBase implements ComportamientoMesa
{
    public function ocupar(Mesa $mesa): EstadoMesa
    {
        throw $this->transicionInvalida($mesa, 'ocupar');
    }

    public function reservar(Mesa $mesa): EstadoMesa
    {
        throw $this->transicionInvalida($mesa, 'reservar');
    }

    public function liberar(Mesa $mesa): EstadoMesa
    {
        throw $this->transicionInvalida($mesa, 'liberar');
    }

    public function enviarAMantenimiento(Mesa $mesa): EstadoMesa
    {
        throw $this->transicionInvalida($mesa, 'enviar a mantenimiento');
    }

    public function habilitar(Mesa $mesa): EstadoMesa
    {
        throw $this->transicionInvalida($mesa, 'habilitar');
    }

    protected function transicionInvalida(Mesa $mesa, string $accion): TransicionMesaException
    {
        return new TransicionMesaException($mesa->estado, $accion);
    }
}
