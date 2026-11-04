<?php

namespace Tests\Unit;

use App\Enums\EstadoMesa;
use App\Estados\Mesa\MesaEnMantenimiento;
use App\Estados\Mesa\MesaLibre;
use App\Estados\Mesa\MesaOcupada;
use App\Estados\Mesa\MesaReservada;
use App\Exceptions\TransicionMesaException;
use App\Models\Mesa;
use Tests\TestCase;

/** Patrón State: cada estado decide qué transiciones permite. */
class EstadoMesaTest extends TestCase
{
    private function mesa(EstadoMesa $estado): Mesa
    {
        return (new Mesa(['numero' => 1, 'capacidad' => 4]))->forceFill(['estado' => $estado]);
    }

    public function test_cada_estado_tiene_su_clase_de_comportamiento(): void
    {
        $this->assertInstanceOf(MesaLibre::class, EstadoMesa::LIBRE->comportamiento());
        $this->assertInstanceOf(MesaOcupada::class, EstadoMesa::OCUPADA->comportamiento());
        $this->assertInstanceOf(MesaReservada::class, EstadoMesa::RESERVADA->comportamiento());
        $this->assertInstanceOf(MesaEnMantenimiento::class, EstadoMesa::MANTENIMIENTO->comportamiento());
    }

    public function test_transiciones_permitidas(): void
    {
        $libre = $this->mesa(EstadoMesa::LIBRE);
        $this->assertSame(EstadoMesa::OCUPADA, $libre->estadoActual()->ocupar($libre));
        $this->assertSame(EstadoMesa::RESERVADA, $libre->estadoActual()->reservar($libre));
        $this->assertSame(EstadoMesa::MANTENIMIENTO, $libre->estadoActual()->enviarAMantenimiento($libre));

        $reservada = $this->mesa(EstadoMesa::RESERVADA);
        $this->assertSame(EstadoMesa::OCUPADA, $reservada->estadoActual()->ocupar($reservada));
        $this->assertSame(EstadoMesa::LIBRE, $reservada->estadoActual()->liberar($reservada));

        $ocupada = $this->mesa(EstadoMesa::OCUPADA);
        $this->assertSame(EstadoMesa::LIBRE, $ocupada->estadoActual()->liberar($ocupada));

        $mantenimiento = $this->mesa(EstadoMesa::MANTENIMIENTO);
        $this->assertSame(EstadoMesa::LIBRE, $mantenimiento->estadoActual()->habilitar($mantenimiento));
    }

    public function test_una_mesa_libre_no_se_puede_liberar(): void
    {
        $mesa = $this->mesa(EstadoMesa::LIBRE);

        $this->expectException(TransicionMesaException::class);
        $this->expectExceptionMessage('No se puede liberar una mesa que está en estado "Libre".');

        $mesa->estadoActual()->liberar($mesa);
    }

    public function test_una_mesa_ocupada_no_se_puede_reservar_ni_enviar_a_mantenimiento(): void
    {
        $mesa = $this->mesa(EstadoMesa::OCUPADA);

        $this->assertSame(['liberar'], $mesa->accionesPermitidas());
    }

    public function test_acciones_permitidas_por_estado(): void
    {
        $this->assertSame(['ocupar', 'reservar', 'enviarAMantenimiento'], $this->mesa(EstadoMesa::LIBRE)->accionesPermitidas());
        $this->assertSame(['ocupar', 'liberar'], $this->mesa(EstadoMesa::RESERVADA)->accionesPermitidas());
        $this->assertSame(['habilitar'], $this->mesa(EstadoMesa::MANTENIMIENTO)->accionesPermitidas());
    }
}
