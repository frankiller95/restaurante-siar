<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PedidoRepositoryInterface;
use App\Enums\EstadoPedido;
use App\Models\DetallePedido;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PedidoRepository implements PedidoRepositoryInterface
{
    private const RELACIONES = ['mesa', 'usuario:id_usuario,nombres', 'detalles.producto'];

    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        $estado = $filtros['estado'] ?? null;

        return Pedido::query()
            ->with(self::RELACIONES)
            ->when($estado === 'abiertos', fn ($q) => $q->whereIn('estado', EstadoPedido::valoresAbiertos()))
            ->when($estado && $estado !== 'abiertos', fn ($q) => $q->where('estado', $estado))
            ->when($filtros['id_mesa'] ?? null, fn ($q, $idMesa) => $q->where('id_mesa', $idMesa))
            ->when($filtros['id_usuario'] ?? null, fn ($q, $idUsuario) => $q->where('id_usuario', $idUsuario))
            ->when($filtros['fecha'] ?? null, function ($q, string $fecha) {
                $dia = Carbon::parse($fecha, config('siar.zona_horaria'));
                $q->whereBetween('fecha_creacion', [
                    $dia->copy()->startOfDay()->utc(),
                    $dia->copy()->endOfDay()->utc(),
                ]);
            })
            ->orderByDesc('fecha_creacion')
            ->orderByDesc('id_pedido')
            ->paginate($porPagina);
    }

    public function colaDeCocina(): Collection
    {
        return Pedido::query()
            ->with(self::RELACIONES)
            ->whereNotNull('fecha_envio_cocina')
            ->whereIn('estado', [
                EstadoPedido::PENDIENTE->value,
                EstadoPedido::EN_PREPARACION->value,
                EstadoPedido::LISTO->value,
            ])
            ->orderBy('fecha_envio_cocina')
            ->get();
    }

    public function crear(array $datos): Pedido
    {
        return Pedido::create($datos)->refresh();
    }

    public function agregarLinea(Pedido $pedido, array $datos): DetallePedido
    {
        return $pedido->detalles()->create($datos);
    }

    public function lineaIgual(Pedido $pedido, int $idProducto, ?string $observacion, string $precio): ?DetallePedido
    {
        return $pedido->detalles()
            ->where('id_producto', $idProducto)
            ->where('precio_unitario', $precio)
            ->when($observacion === null, fn ($q) => $q->whereNull('observacion'), fn ($q) => $q->where('observacion', $observacion))
            ->first();
    }

    public function cargarDetalle(Pedido $pedido): Pedido
    {
        return $pedido->refresh()->load(self::RELACIONES);
    }
}
