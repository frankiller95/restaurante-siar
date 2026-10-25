<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\BitacoraRepositoryInterface;
use App\Models\BitacoraAuditoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BitacoraRepository implements BitacoraRepositoryInterface
{
    public function registrar(array $datos): BitacoraAuditoria
    {
        return BitacoraAuditoria::create($datos);
    }

    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return BitacoraAuditoria::query()
            ->with('usuario:id_usuario,nombres,correo')
            ->when($filtros['tipo_evento'] ?? null, fn ($q, $tipo) => $q->where('tipo_evento', $tipo))
            ->when($filtros['buscar'] ?? null, fn ($q, $texto) => $q->where('descripcion', 'like', "%{$texto}%"))
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha_hora', '<=', $hasta))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_bitacora')
            ->paginate($porPagina);
    }
}
