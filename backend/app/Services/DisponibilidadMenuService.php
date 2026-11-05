<?php

namespace App\Services;

use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Models\DetalleReceta;
use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * RF40: agota o repone productos del menú según el stock de los insumos de su receta.
 *
 * - Si un insumo de la receta no alcanza para una porción, el producto queda
 *   no disponible y marcado como "agotado por inventario".
 * - Cuando vuelve a alcanzar, solo se reactivan los productos que agotó el
 *   inventario; los que el cocinero marcó a mano (CU05) no se tocan.
 */
class DisponibilidadMenuService
{
    /**
     * @param  list<int>  $idsInsumo  insumos cuyo stock cambió
     * @param  list<int>  $idsProducto  productos cuya receta cambió
     */
    public function actualizar(array $idsInsumo, array $idsProducto = []): void
    {
        $this->productosAfectados($idsInsumo, $idsProducto)->each(fn (Producto $producto) => $this->evaluar($producto));
    }

    public function evaluar(Producto $producto): void
    {
        $alcanza = $this->alcanzaParaUnaPorcion($producto);

        if (! $alcanza && $producto->disponible) {
            $producto->marcarAgotadoPorInventario(true);
            EventoAuditable::dispatch(
                TipoEvento::PRODUCTO_AGOTADO_INVENTARIO,
                "{$producto->nombre} quedó no disponible: no hay insumos suficientes para una porción.",
            );
        } elseif ($alcanza && ! $producto->disponible && $producto->agotado_por_inventario) {
            $producto->marcarAgotadoPorInventario(false);
            EventoAuditable::dispatch(
                TipoEvento::PRODUCTO_REPUESTO_INVENTARIO,
                "{$producto->nombre} volvió a estar disponible: se repusieron sus insumos.",
            );
        }
    }

    /** Sin receta, siempre alcanza. Los insumos dados de baja no se tienen en cuenta. */
    public function alcanzaParaUnaPorcion(Producto $producto): bool
    {
        return $producto->receta->every(
            fn (DetalleReceta $linea) => ! $linea->insumo->activo || $linea->insumo->alcanzaPara((float) $linea->cantidad_por_porcion),
        );
    }

    /** @return Collection<int, Producto> */
    private function productosAfectados(array $idsInsumo, array $idsProducto): Collection
    {
        if ($idsInsumo === [] && $idsProducto === []) {
            return collect();
        }

        return Producto::query()
            ->activos()
            ->with('receta.insumo')
            ->where(function ($q) use ($idsInsumo, $idsProducto) {
                $q->whereHas('receta', fn ($r) => $r->whereIn('id_insumo', $idsInsumo ?: [0]))
                    ->orWhereIn('id_producto', $idsProducto ?: [0]);
            })
            ->get();
    }
}
