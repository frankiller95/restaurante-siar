<?php

namespace App\Events;

use App\Models\Insumo;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Patrón Observer (RF40): un insumo llegó a su stock mínimo.
 * AlertaStockObserver registra la alerta.
 */
class StockBajo
{
    use Dispatchable;

    public function __construct(public readonly Insumo $insumo) {}
}
