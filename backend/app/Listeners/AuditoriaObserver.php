<?php

namespace App\Listeners;

use App\Contracts\Repositories\BitacoraRepositoryInterface;
use App\Events\EventoAuditable;
use Illuminate\Support\Str;

/**
 * Observador de auditoría: escucha EventoAuditable y lo guarda en la bitácora.
 * Laravel lo registra automáticamente por el tipo del parámetro de handle().
 */
class AuditoriaObserver
{
    public function __construct(private readonly BitacoraRepositoryInterface $bitacora) {}

    public function handle(EventoAuditable $evento): void
    {
        $this->bitacora->registrar([
            'tipo_evento' => $evento->tipo->value,
            'descripcion' => Str::limit($evento->descripcion, 497),
            'fecha_hora' => now(),
            'ip_origen' => request()?->ip(),
            'id_usuario' => $evento->idUsuario ?? auth()->id(),
        ]);
    }
}
