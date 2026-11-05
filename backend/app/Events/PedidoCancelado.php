<?php

namespace App\Events;

use App\Models\Pedido;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Patrón Observer (RF20): se publica al cancelar un pedido. El Módulo 5
 * (Inventario) lo escuchará: si ya se había enviado a cocina y no estaba en
 * preparación, reintegra los insumos; si estaba en preparación, registra merma (RF39).
 */
class PedidoCancelado
{
    use Dispatchable;

    public function __construct(
        public readonly Pedido $pedido,
        public readonly bool $fueEnviadoACocina,
        public readonly bool $estabaEnPreparacion,
    ) {}
}
