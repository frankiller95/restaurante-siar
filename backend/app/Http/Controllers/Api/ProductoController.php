<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ProductoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\CambiarCategoriaRequest;
use App\Http\Requests\Menu\CambiarDisponibilidadRequest;
use App\Http\Requests\Menu\GuardarProductoRequest;
use App\Http\Resources\CategoriaResource;
use App\Http\Resources\ProductoResource;
use App\Models\Producto;
use App\Services\ProductoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * CU01 a CU05 del Módulo 2 (Gestión de Menú).
 */
class ProductoController extends Controller
{
    public function __construct(private readonly ProductoService $productos) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'id_categoria' => ['nullable', 'integer'],
            'disponible' => ['nullable', 'boolean'],
            'estado' => ['nullable', Rule::in(['activos', 'baja', 'todos'])],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        if (isset($filtros['disponible'])) {
            $filtros['disponible'] = $request->boolean('disponible');
        }

        return ProductoResource::collection(
            $this->productos->listar($filtros, (int) ($filtros['por_pagina'] ?? 15))
        );
    }

    public function show(Producto $producto): ProductoResource
    {
        return new ProductoResource($producto->load('categoria'));
    }

    /** CU01 Crear producto. */
    public function store(GuardarProductoRequest $request): JsonResponse
    {
        $producto = $this->productos->crear(
            ProductoDTO::desdeArray($request->validated()),
            $request->file('imagen'),
        );

        return response()->json([
            'message' => 'Producto creado. Ya está disponible en el menú.',
            'data' => new ProductoResource($producto),
        ], 201);
    }

    /** CU02 Modificar producto. */
    public function update(GuardarProductoRequest $request, Producto $producto): JsonResponse
    {
        $producto = $this->productos->actualizar(
            $producto,
            ProductoDTO::desdeArray($request->validated()),
            $request->file('imagen'),
            $request->boolean('quitar_imagen'),
        );

        return response()->json([
            'message' => 'Producto actualizado.',
            'data' => new ProductoResource($producto),
        ]);
    }

    /** CU03 Eliminar producto (baja lógica). */
    public function destroy(Producto $producto): JsonResponse
    {
        $this->productos->eliminar($producto);

        return response()->json(['message' => 'Producto dado de baja. Ya no aparece en el menú, pero se conserva en el historial.']);
    }

    /** CU04 Categorizar producto. */
    public function cambiarCategoria(CambiarCategoriaRequest $request, Producto $producto): JsonResponse
    {
        $producto = $this->productos->cambiarCategoria($producto, (int) $request->validated('id_categoria'));

        return response()->json([
            'message' => "El producto quedó en la categoría {$producto->categoria->nombre}.",
            'data' => new ProductoResource($producto),
        ]);
    }

    /** CU05 Gestionar disponibilidad. */
    public function cambiarDisponibilidad(CambiarDisponibilidadRequest $request, Producto $producto): JsonResponse
    {
        $producto = $this->productos->cambiarDisponibilidad($producto, $request->boolean('disponible'));

        return response()->json([
            'message' => $producto->disponible
                ? "{$producto->nombre} está disponible otra vez."
                : "{$producto->nombre} quedó como no disponible (agotado).",
            'data' => new ProductoResource($producto),
        ]);
    }

    /** Carta del menú agrupada por categoría (meseros, cajeros, cocina). */
    public function menu(): AnonymousResourceCollection
    {
        return CategoriaResource::collection($this->productos->menu());
    }
}
