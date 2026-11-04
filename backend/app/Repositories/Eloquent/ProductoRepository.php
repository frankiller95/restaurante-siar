<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProductoRepositoryInterface;
use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductoRepository implements ProductoRepositoryInterface
{
    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        $estado = $filtros['estado'] ?? 'activos';

        return Producto::query()
            ->with('categoria')
            ->when($estado === 'activos', fn ($q) => $q->activos())
            ->when($estado === 'baja', fn ($q) => $q->dadosDeBaja())
            ->when($filtros['buscar'] ?? null, function ($q, string $texto) {
                $q->where(fn ($w) => $w->where('nombre', 'like', "%{$texto}%")
                    ->orWhere('descripcion', 'like', "%{$texto}%"));
            })
            ->when($filtros['id_categoria'] ?? null, fn ($q, $id) => $q->where('id_categoria', $id))
            ->when(isset($filtros['disponible']), fn ($q) => $q->where('disponible', (bool) $filtros['disponible']))
            ->orderBy('nombre')
            ->paginate($porPagina);
    }

    public function obtenerPorId(int $idProducto): ?Producto
    {
        return Producto::with('categoria')->find($idProducto);
    }

    public function existeNombreEnCategoria(string $nombre, int $idCategoria, ?int $exceptoId = null): bool
    {
        return Producto::activos()
            ->where('id_categoria', $idCategoria)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($exceptoId, fn ($q) => $q->where('id_producto', '!=', $exceptoId))
            ->exists();
    }

    /**
     * Las tablas de pedidos llegan con el Módulo 4. Mientras no existan, el
     * producto no tiene pedidos pendientes. Ver config/siar.php > pedidos.
     */
    public function tienePedidosPendientes(Producto $producto): bool
    {
        $config = config('siar.pedidos');

        if (! Schema::hasTable($config['tabla_pedidos']) || ! Schema::hasTable($config['tabla_detalle'])) {
            return false;
        }

        return DB::table($config['tabla_detalle'].' as d')
            ->join($config['tabla_pedidos'].' as p', 'p.id_pedido', '=', 'd.id_pedido')
            ->where('d.id_producto', $producto->id_producto)
            ->whereIn('p.estado', $config['estados_pendientes'])
            ->exists();
    }

    public function crear(array $datos): Producto
    {
        return Producto::create($datos)->load('categoria');
    }

    public function actualizar(Producto $producto, array $datos): Producto
    {
        $producto->fill($datos)->save();

        return $producto->load('categoria');
    }
}
