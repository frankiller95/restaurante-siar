<?php

namespace App\Events;

use App\Enums\TipoEvento;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Patrón Observer: los servicios publican este evento cuando ocurre algo que
 * debe quedar en la bitácora, sin conocer a quién le interesa. El observador
 * App\Listeners\AuditoriaObserver lo recibe y lo registra.
 */
class EventoAuditable
{
    use Dispatchable;

    /**
     * @param  int|null  $idUsuario  usuario que realizó la acción (null = usuario autenticado)
     */
    public function __construct(
        public readonly TipoEvento $tipo,
        public readonly string $descripcion,
        public readonly ?int $idUsuario = null,
    ) {}
}
