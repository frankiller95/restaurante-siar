<?php

namespace App\Listeners;

use App\Events\StockActualizado;
use App\Services\DisponibilidadMenuService;

/**
 * Patrón Observer (RF40): cada cambio de stock o de receta pone al día la
 * disponibilidad de los productos del menú.
 */
class DisponibilidadMenuObserver
{
    public function __construct(private readonly DisponibilidadMenuService $disponibilidad) {}

    public function handle(StockActualizado $evento): void
    {
        $this->disponibilidad->actualizar($evento->idsInsumo, $evento->idsProducto);
    }
}
