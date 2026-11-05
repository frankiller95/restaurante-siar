<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\GuardarRecetaRequest;
use App\Models\DetalleReceta;
use App\Models\Producto;
use App\Services\DisponibilidadMenuService;
use App\Services\RecetaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * RF38: receta de un producto del menú.
 */
class RecetaController extends Controller
{
    public function __construct(
        private readonly RecetaService $recetas,
        private readonly DisponibilidadMenuService $disponibilidad,
    ) {}

    public function show(Producto $producto): JsonResponse
    {
        return $this->respuesta($producto, $this->recetas->receta($producto));
    }

    public function update(GuardarRecetaRequest $request, Producto $producto): JsonResponse
    {
        $receta = $this->recetas->guardar($producto, $request->validated('insumos'));

        return $this->respuesta($producto->refresh(), $receta, "Receta de {$producto->nombre} guardada.");
    }

    /** @param Collection<int, DetalleReceta> $receta */
    private function respuesta(Producto $producto, Collection $receta, ?string $mensaje = null): JsonResponse
    {
        $producto->load('receta.insumo');

        return response()->json(array_filter([
            'message' => $mensaje,
            'producto' => [
                'id_producto' => $producto->id_producto,
                'nombre' => $producto->nombre,
                'disponible' => $producto->disponible,
                'agotado_por_inventario' => $producto->agotado_por_inventario,
                'alcanza_para_una_porcion' => $this->disponibilidad->alcanzaParaUnaPorcion($producto),
            ],
            'insumos' => $receta->map(fn (DetalleReceta $linea) => [
                'id_insumo' => $linea->id_insumo,
                'nombre' => $linea->insumo->nombre,
                'unidad' => $linea->insumo->unidad_medida->abreviatura(),
                'cantidad_por_porcion' => (float) $linea->cantidad_por_porcion,
                'stock_actual' => (float) $linea->insumo->stock_actual,
                'costo_por_porcion' => round((float) $linea->cantidad_por_porcion * (float) $linea->insumo->costo_unitario, 2),
            ])->values(),
            'costo_por_porcion' => round($receta->sum(fn ($l) => (float) $l->cantidad_por_porcion * (float) $l->insumo->costo_unitario), 2),
        ], fn ($valor) => $valor !== null));
    }
}
