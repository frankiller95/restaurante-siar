<?php

namespace App\Exceptions;

use App\Enums\EstadoPedido;

/** El pedido no puede pasar al estado pedido desde su estado actual. */
class TransicionPedidoException extends ReglaNegocioException
{
    public function __construct(EstadoPedido $actual, string $accion)
    {
        parent::__construct(
            "No se puede {$accion} un pedido que está \"{$actual->etiqueta()}\".",
            409,
            'transicion_invalida',
        );
    }
}
