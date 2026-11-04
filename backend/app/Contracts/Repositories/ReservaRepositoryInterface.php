<?php

namespace App\Contracts\Repositories;

use App\Models\Reserva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface ReservaRepositoryInterface
{
    /**
     * @param  array{fecha?: string|null, estado?: string|null, id_mesa?: int|null, buscar?: string|null}  $filtros
     */
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Reserva;

    /** RF15: ¿otra reserva pendiente de la mesa se cruza con este horario? */
    public function hayCruce(int $idMesa, Carbon $inicio, ?int $exceptoId = null): bool;

    /** @return Collection<int, Reserva> pendientes de la mesa (las más próximas primero) */
    public function pendientesDeMesa(int $idMesa): Collection;

    /**
     * Pendientes cuya hora está entre (ahora - tolerancia) y (ahora + ventana):
     * sus mesas deben estar "Reservadas".
     *
     * @return Collection<int, Reserva>
     */
    public function enVentana(Carbon $ahora): Collection;

    /**
     * Pendientes cuya hora + tolerancia ya pasó sin que llegara el cliente.
     *
     * @return Collection<int, Reserva>
     */
    public function vencidas(Carbon $ahora): Collection;
}
