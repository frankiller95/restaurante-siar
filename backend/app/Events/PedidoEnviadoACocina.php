<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Patrón Observer (RF21): se publica cuando un pedido entra a la cola de cocina.
 * El Módulo 5 (Inventario) lo escuchará para descontar los insumos de las
 * recetas (RF39) sin que el módulo de Pedidos conozca al inventario.
 */
class PedidoEnviadoACocina
{
    use Dispatchable;

    public function __construct(public readonly Pedido $pedido) {}
}
