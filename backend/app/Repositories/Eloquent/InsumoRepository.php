<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\InsumoRepositoryInterface;
use App\Enums\TipoMovimiento;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InsumoRepository implements InsumoRepositoryInterface
{
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        $estado = $filtros['estado'] ?? 'activos';

        return Insumo::query()
            ->withCount('recetas')
            ->when($estado === 'activos', fn ($q) => $q->activos())
            ->when($estado === 'baja', fn ($q) => $q->dadosDeBaja())
            ->when($filtros['buscar'] ?? null, fn ($q, $texto) => $q->where('nombre', 'like', "%{$texto}%"))
            ->when($filtros['bajo_minimo'] ?? false, fn ($q) => $q->whereColumn('stock_actual', '<=', 'stock_minimo'))
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    public function obtenerPorId(int $idInsumo): ?Insumo
    {
        return Insumo::find($idInsumo);
    }

    public function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return Insumo::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($exceptoId, fn ($q) => $q->where('id_insumo', '!=', $exceptoId))
            ->exists();
    }

    public function crear(array $datos): Insumo
    {
        return Insumo::create($datos)->refresh();
    }

    public function actualizar(Insumo $insumo, array $datos): Insumo
    {
        $insumo->fill($datos)->save();

        return $insumo;
    }

    public function eliminar(Insumo $insumo): void
    {
        $insumo->delete();
    }

    public function registrarMovimiento(array $datos): MovimientoInventario
    {
        return MovimientoInventario::create($datos);
    }

    public function movimientos(Insumo $insumo, array $filtros, int $porPagina = 30): LengthAwarePaginator
    {
        $zona = config('siar.zona_horaria');

        return $insumo->movimientos()
            ->with(['usuario:id_usuario,nombres'])
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->where('fecha', '>=', Carbon::parse($desde, $zona)->startOfDay()->utc()))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->where('fecha', '<=', Carbon::parse($hasta, $zona)->endOfDay()->utc()))
            ->orderByDesc('fecha')
            ->orderByDesc('id_movimiento')
            ->paginate($porPagina);
    }

    public function bajoMinimo(): Collection
    {
        return Insumo::activos()
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderBy('nombre')
            ->get();
    }

    public function consumoNetoDePedido(int $idPedido): array
    {
        return MovimientoInventario::where('id_pedido', $idPedido)
            ->whereIn('tipo', [TipoMovimiento::CONSUMO_PEDIDO->value, TipoMovimiento::REINTEGRO_PEDIDO->value])
            ->get()
            ->groupBy('id_insumo')
            ->map(fn (Collection $movimientos) => round(-$movimientos->sum(fn ($m) => (float) $m->cantidad), 3))
            ->filter(fn (float $cantidad) => $cantidad > 0)
            ->all();
    }

    public function usadoEnRecetasActivas(Insumo $insumo): bool
    {
        return $insumo->recetas()->whereHas('producto', fn ($q) => $q->where('activo', true))->exists();
    }
}
