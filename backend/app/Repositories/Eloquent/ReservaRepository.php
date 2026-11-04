<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ReservaRepositoryInterface;
use App\Enums\EstadoReserva;
use App\Models\Reserva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Las comparaciones de horario se hacen en PHP sobre las reservas pendientes de
 * pocos días alrededor de la fecha, porque "fecha" y "hora" están separadas y en
 * la zona horaria del restaurante. Así funciona igual en MySQL y en SQLite.
 */
class ReservaRepository implements ReservaRepositoryInterface
{
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return Reserva::query()
            ->with(['mesa', 'usuario:id_usuario,nombres'])
            ->when($filtros['fecha'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', $fecha))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['id_mesa'] ?? null, fn ($q, $idMesa) => $q->where('id_mesa', $idMesa))
            ->when($filtros['buscar'] ?? null, function ($q, string $texto) {
                $q->where(fn ($w) => $w->where('cliente_nombre', 'like', "%{$texto}%")
                    ->orWhere('cliente_telefono', 'like', "%{$texto}%"));
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->paginate($porPagina);
    }

    public function crear(array $datos): Reserva
    {
        return Reserva::create($datos)->refresh()->load(['mesa', 'usuario:id_usuario,nombres']);
    }

    public function hayCruce(int $idMesa, Carbon $inicio, ?int $exceptoId = null): bool
    {
        $duracion = config('siar.reservas.duracion_minutos');

        return $this->pendientesCerca($inicio)
            ->where('id_mesa', $idMesa)
            ->when($exceptoId, fn ($q) => $q->where('id_reserva', '!=', $exceptoId))
            ->get()
            ->contains(fn (Reserva $reserva) => abs($reserva->inicio()->diffInMinutes($inicio, false)) < $duracion);
    }

    public function pendientesDeMesa(int $idMesa): Collection
    {
        return Reserva::where('id_mesa', $idMesa)
            ->where('estado', EstadoReserva::PENDIENTE->value)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();
    }

    public function enVentana(Carbon $ahora): Collection
    {
        $desde = $ahora->copy()->subMinutes(config('siar.reservas.tolerancia_minutos'));
        $hasta = $ahora->copy()->addMinutes(config('siar.reservas.ventana_minutos'));

        return $this->pendientesCerca($ahora)
            ->with('mesa')
            ->get()
            ->filter(fn (Reserva $reserva) => $reserva->inicio()->between($desde, $hasta))
            ->values();
    }

    public function vencidas(Carbon $ahora): Collection
    {
        $tolerancia = config('siar.reservas.tolerancia_minutos');
        $hoy = $ahora->copy()->setTimezone(config('siar.zona_horaria'))->addDay()->toDateString();

        return Reserva::where('estado', EstadoReserva::PENDIENTE->value)
            ->whereDate('fecha', '<=', $hoy)
            ->with('mesa')
            ->get()
            ->filter(fn (Reserva $reserva) => $reserva->inicio()->addMinutes($tolerancia)->lt($ahora))
            ->values();
    }

    /** Reservas pendientes del día anterior, el mismo día y el siguiente (hora del restaurante). */
    private function pendientesCerca(Carbon $momento): Builder
    {
        $local = $momento->copy()->setTimezone(config('siar.zona_horaria'));

        return Reserva::query()
            ->where('estado', EstadoReserva::PENDIENTE->value)
            ->whereDate('fecha', '>=', $local->copy()->subDay()->toDateString())
            ->whereDate('fecha', '<=', $local->copy()->addDay()->toDateString());
    }
}
