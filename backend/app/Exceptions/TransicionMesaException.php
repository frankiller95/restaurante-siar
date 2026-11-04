<?php

namespace App\Exceptions;

use App\Enums\EstadoMesa;

/**
 * Patrón State: la mesa no permite esa acción en su estado actual
 * (por ejemplo, liberar una mesa que ya está libre).
 */
class TransicionMesaException extends ReglaNegocioException
{
    public function __construct(EstadoMesa $estado, string $accion)
    {
        parent::__construct(
            "No se puede {$accion} una mesa que está en estado \"{$estado->etiqueta()}\".",
            409,
            'transicion_invalida',
        );
    }
}
