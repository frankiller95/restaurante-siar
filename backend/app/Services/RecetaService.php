<?php

namespace App\Services;

use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Events\StockActualizado;
use App\Exceptions\ReglaNegocioException;
use App\Models\DetalleReceta;
use App\Models\Insumo;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF38: receta de cada producto del menú (insumos y cantidad por porción).
 */
class RecetaService
{
    /** @return Collection<int, DetalleReceta> */
    public function receta(Producto $producto): Collection
    {
        return $producto->receta()->with('insumo')->get()->sortBy('insumo.nombre')->values();
    }

    /**
     * Reemplaza la receta completa. Una lista vacía deja el producto sin receta
     * (no descuenta inventario).
     *
     * @param  list<array{id_insumo: int, cantidad_por_porcion: float}>  $lineas
     * @return Collection<int, DetalleReceta>
     */
    public function guardar(Producto $producto, array $lineas): Collection
    {
        if (! $producto->estaActivo()) {
            throw new ReglaNegocioException('El producto está dado de baja.');
        }

        $ids = array_map(fn ($linea) => (int) $linea['id_insumo'], $lineas);
        $inactivos = Insumo::whereIn('id_insumo', $ids)->where('activo', false)->pluck('nombre');
        if ($inactivos->isNotEmpty()) {
            throw ValidationException::withMessages([
                'insumos' => 'Estos insumos están dados de baja: '.$inactivos->implode(', ').'.',
            ]);
        }

        $anteriores = $producto->receta()->pluck('id_insumo')->all();

        DB::transaction(function () use ($producto, $lineas, $ids, $anteriores) {
            $producto->receta()->delete();

            foreach ($lineas as $linea) {
                $producto->receta()->create([
                    'id_insumo' => (int) $linea['id_insumo'],
                    'cantidad_por_porcion' => round((float) $linea['cantidad_por_porcion'], 3),
                ]);
            }

            // El producto puede pasar a estar o no disponible con la receta nueva (RF40).
            StockActualizado::dispatch(array_values(array_unique([...$anteriores, ...$ids])), [$producto->id_producto]);
        });

        EventoAuditable::dispatch(
            TipoEvento::RECETA_ACTUALIZADA,
            "Se actualizó la receta de {$producto->nombre} (".count($lineas).' insumos).',
        );

        return $this->receta($producto);
    }
}
