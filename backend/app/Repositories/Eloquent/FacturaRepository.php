<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\FacturaRepositoryInterface;
use App\Enums\EstadoPedido;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FacturaRepository implements FacturaRepositoryInterface
{
    private const RELACIONES = ['pedido.mesa', 'usuario:id_usuario,nombres', 'detalles', 'pagos.usuario:id_usuario,nombres'];

    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        $zona = config('siar.zona_horaria');

        return Factura::query()
            ->with(['pedido.mesa', 'usuario:id_usuario,nombres', 'pagos'])
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['buscar'] ?? null, function ($q, string $texto) {
                $q->where(fn ($q) => $q
                    ->where('numero', 'like', "%{$texto}%")
                    ->orWhere('cliente_nombre', 'like', "%{$texto}%")
                    ->orWhere('cliente_documento', 'like', "%{$texto}%"));
            })
            ->when($filtros['desde'] ?? null, fn ($q, $desde) => $q->where('fecha_emision', '>=', Carbon::parse($desde, $zona)->startOfDay()->utc()))
            ->when($filtros['hasta'] ?? null, fn ($q, $hasta) => $q->where('fecha_emision', '<=', Carbon::parse($hasta, $zona)->endOfDay()->utc()))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id_factura')
            ->paginate($porPagina);
    }

    public function pedidosPorFacturar(): Collection
    {
        return Pedido::query()
            ->with(['mesa', 'usuario:id_usuario,nombres', 'detalles.producto', 'detalles.adiciones'])
            ->where('estado', EstadoPedido::ENTREGADO->value)
            ->whereDoesntHave('facturas', fn ($q) => $q->vigentes())
            ->orderBy('fecha_entrega')
            ->get();
    }

    public function pedidoParaFacturar(int $idPedido): ?Pedido
    {
        return Pedido::query()->whereKey($idPedido)->lockForUpdate()->first();
    }

    public function facturaVigenteDePedido(int $idPedido): ?Factura
    {
        return Factura::query()->where('id_pedido', $idPedido)->vigentes()->latest('id_factura')->first();
    }

    public function bloquear(Factura $factura): Factura
    {
        return Factura::query()->whereKey($factura->getKey())->lockForUpdate()->firstOrFail();
    }

    public function crear(array $datos): Factura
    {
        return Factura::create($datos)->refresh();
    }

    public function actualizar(Factura $factura, array $datos): Factura
    {
        $factura->forceFill($datos)->save();

        return $factura;
    }

    public function agregarLineas(Factura $factura, array $lineas): void
    {
        $factura->detalles()->createMany($lineas);
    }

    public function registrarPago(Factura $factura, array $datos): Pago
    {
        return $factura->pagos()->create($datos);
    }

    public function cargarDetalle(Factura $factura): Factura
    {
        return $factura->refresh()->load(self::RELACIONES);
    }
}
