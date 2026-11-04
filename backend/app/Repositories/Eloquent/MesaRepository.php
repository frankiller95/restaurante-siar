<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MesaRepositoryInterface;
use App\Enums\EstadoMesa;
use App\Models\Mesa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MesaRepository implements MesaRepositoryInterface
{
    public function buscar(array $filtros = []): Collection
    {
        return Mesa::query()
            ->with('proximaReserva')
            ->when(! ($filtros['incluir_baja'] ?? false), fn ($q) => $q->activos())
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['ubicacion'] ?? null, fn ($q, $ubicacion) => $q->where('ubicacion', $ubicacion))
            ->orderBy('numero')
            ->get();
    }

    public function resumenPorEstado(): array
    {
        $conteo = Mesa::activos()
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $resumen = [];
        foreach (EstadoMesa::cases() as $estado) {
            $resumen[$estado->value] = (int) ($conteo[$estado->value] ?? 0);
        }

        return $resumen;
    }

    public function obtenerPorId(int $idMesa): ?Mesa
    {
        return Mesa::find($idMesa);
    }

    public function existeNumero(int $numero, ?int $exceptoId = null): bool
    {
        return Mesa::where('numero', $numero)
            ->when($exceptoId, fn ($q) => $q->where('id_mesa', '!=', $exceptoId))
            ->exists();
    }

    public function crear(array $datos): Mesa
    {
        return Mesa::create($datos)->refresh();
    }

    public function actualizar(Mesa $mesa, array $datos): Mesa
    {
        $mesa->fill($datos)->save();

        return $mesa;
    }

    /**
     * Las tablas de pedidos llegan con el Módulo 4. Mientras no existan, la mesa
     * no tiene pedidos abiertos. Ver config/siar.php > pedidos.
     */
    public function tienePedidosAbiertos(Mesa $mesa): bool
    {
        $config = config('siar.pedidos');

        if (! Schema::hasTable($config['tabla_pedidos']) || ! Schema::hasColumn($config['tabla_pedidos'], 'id_mesa')) {
            return false;
        }

        return DB::table($config['tabla_pedidos'])
            ->where('id_mesa', $mesa->id_mesa)
            ->whereIn('estado', $config['estados_abiertos'])
            ->exists();
    }

    public function candidatasParaReserva(int $personas): Collection
    {
        return Mesa::activos()
            ->where('estado', '!=', EstadoMesa::MANTENIMIENTO->value)
            ->where('capacidad', '>=', $personas)
            ->orderBy('capacidad')
            ->orderBy('numero')
            ->get();
    }
}
