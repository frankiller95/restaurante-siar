<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Patrón Observer (RF40): cambió el stock de estos insumos, o la receta de
 * estos productos. DisponibilidadMenuObserver revisa qué productos se pueden preparar.
 */
class StockActualizado
{
    use Dispatchable;

    /**
     * @param  list<int>  $idsInsumo
     * @param  list<int>  $idsProducto
     */
    public function __construct(
        public readonly array $idsInsumo,
        public readonly array $idsProducto = [],
    ) {}
}
