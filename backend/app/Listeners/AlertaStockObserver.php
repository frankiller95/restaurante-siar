<?php

namespace App\Listeners;

use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Events\StockBajo;

/**
 * Patrón Observer (RF40): un insumo llegó a su stock mínimo. La alerta queda
 * en la bitácora y el insumo aparece en GET /api/insumos/alertas.
 */
class AlertaStockObserver
{
    public function handle(StockBajo $evento): void
    {
        $insumo = $evento->insumo;
        $unidad = $insumo->unidad_medida->abreviatura();

        EventoAuditable::dispatch(
            TipoEvento::STOCK_BAJO,
            "Stock bajo de {$insumo->nombre}: quedan {$insumo->stock_actual} {$unidad} (mínimo {$insumo->stock_minimo} {$unidad}).",
        );
    }
}
