<?php

namespace App\Listeners;

use App\Events\PedidoCancelado;
use App\Events\PedidoEnviadoACocina;
use App\Services\InventarioService;

/**
 * Patrón Observer (RF39): el inventario reacciona a los eventos del Módulo 4
 * sin que el módulo de Pedidos sepa que existe.
 * Laravel registra cada método handle* según el tipo de evento que recibe.
 */
class InventarioObserver
{
    public function __construct(private readonly InventarioService $inventario) {}

    /** Pedido enviado a cocina → se descuentan los insumos de las recetas. */
    public function handlePedidoEnviadoACocina(PedidoEnviadoACocina $evento): void
    {
        $this->inventario->descontarPorPedido($evento->pedido);
    }

    /**
     * Pedido cancelado → si ya se había enviado a cocina, se reintegran los
     * insumos; si estaba en preparación, se registran como merma.
     */
    public function handlePedidoCancelado(PedidoCancelado $evento): void
    {
        if ($evento->fueEnviadoACocina) {
            $this->inventario->reintegrarPorPedido($evento->pedido, comoMerma: $evento->estabaEnPreparacion);
        }
    }
}
