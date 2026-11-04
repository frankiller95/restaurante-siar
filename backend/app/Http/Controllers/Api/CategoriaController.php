<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CategoriaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\GuardarCategoriaRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use App\Services\CategoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CU04 Categorizar producto: categorías del menú.
 */
class CategoriaController extends Controller
{
    public function __construct(private readonly CategoriaService $categorias) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return CategoriaResource::collection($this->categorias->listar($request->boolean('solo_activas')));
    }

    public function store(GuardarCategoriaRequest $request): JsonResponse
    {
        $categoria = $this->categorias->crear(CategoriaDTO::desdeArray($request->validated()));

        return response()->json([
            'message' => 'Categoría creada.',
            'data' => new CategoriaResource($categoria),
        ], 201);
    }

    public function update(GuardarCategoriaRequest $request, Categoria $categoria): JsonResponse
    {
        $categoria = $this->categorias->actualizar($categoria, CategoriaDTO::desdeArray($request->validated()));

        return response()->json([
            'message' => 'Categoría actualizada.',
            'data' => new CategoriaResource($categoria),
        ]);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        $this->categorias->eliminar($categoria);

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
