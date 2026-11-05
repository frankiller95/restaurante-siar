<?php

namespace App\Http\Controllers\Api;

use App\DTOs\InsumoDTO;
use App\Enums\TipoMovimiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\GuardarInsumoRequest;
use App\Http\Requests\Inventario\MovimientoRequest;
use App\Http\Resources\InsumoResource;
use App\Http\Resources\MovimientoResource;
use App\Models\Insumo;
use App\Services\InventarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Módulo 5: RF23 a RF27 (insumos, movimientos y kárdex).
 */
class InsumoController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    /** RF27: existencias. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['activos', 'baja', 'todos'])],
            'bajo_minimo' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $filtros['bajo_minimo'] = $request->boolean('bajo_minimo');

        return InsumoResource::collection($this->inventario->listar($filtros, (int) ($filtros['por_pagina'] ?? 20)));
    }

    /** RF40: insumos en su stock mínimo o por debajo. */
    public function alertas(): AnonymousResourceCollection
    {
        return InsumoResource::collection($this->inventario->insumosBajoMinimo());
    }

    public function show(Insumo $insumo): InsumoResource
    {
        return new InsumoResource($insumo->loadCount('recetas'));
    }

    /** RF23. */
    public function store(GuardarInsumoRequest $request): JsonResponse
    {
        $insumo = $this->inventario->registrarInsumo(InsumoDTO::desdeArray($request->validated()));

        return response()->json(['message' => "Insumo {$insumo->nombre} registrado.", 'data' => new InsumoResource($insumo)], 201);
    }

    /** RF24. */
    public function update(GuardarInsumoRequest $request, Insumo $insumo): JsonResponse
    {
        $insumo = $this->inventario->actualizarInsumo($insumo, InsumoDTO::desdeArray($request->validated()));

        return response()->json(['message' => "Insumo {$insumo->nombre} actualizado.", 'data' => new InsumoResource($insumo)]);
    }

    /** RF24: desactivar (o borrar si no tiene historial). */
    public function destroy(Insumo $insumo): JsonResponse
    {
        $tipo = $this->inventario->eliminarInsumo($insumo);

        return response()->json([
            'message' => $tipo === InventarioService::ELIMINACION_LOGICA
                ? "{$insumo->nombre} tiene historial, así que se desactivó en lugar de borrarse."
                : "Insumo {$insumo->nombre} eliminado.",
            'tipo_eliminacion' => $tipo,
        ]);
    }

    /** RF25: entrada (compra o reposición). */
    public function entrada(MovimientoRequest $request, Insumo $insumo): JsonResponse
    {
        $datos = $request->validated();
        $movimiento = $this->inventario->registrarEntrada(
            $insumo,
            (float) $datos['cantidad'],
            isset($datos['costo_unitario']) ? (float) $datos['costo_unitario'] : null,
            filled($datos['proveedor'] ?? null) ? trim($datos['proveedor']) : null,
            isset($datos['fecha']) ? Carbon::parse($datos['fecha'], config('siar.zona_horaria')) : null,
        );

        return $this->respuesta($insumo, $movimiento, 'Entrada registrada.', 201);
    }

    /** RF26: merma. */
    public function merma(MovimientoRequest $request, Insumo $insumo): JsonResponse
    {
        $movimiento = $this->inventario->registrarMerma(
            $insumo,
            (float) $request->validated('cantidad'),
            trim($request->validated('motivo')),
        );

        return $this->respuesta($insumo, $movimiento, 'Merma registrada.', 201);
    }

    /** RF26: ajuste por conteo físico. */
    public function ajuste(MovimientoRequest $request, Insumo $insumo): JsonResponse
    {
        $movimiento = $this->inventario->ajustarConteo(
            $insumo,
            (float) $request->validated('stock_contado'),
            trim($request->validated('motivo')),
        );

        return $this->respuesta($insumo, $movimiento, 'Ajuste registrado.', 201);
    }

    /** RF27: kárdex del insumo. */
    public function movimientos(Request $request, Insumo $insumo): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tipo' => ['nullable', Rule::enum(TipoMovimiento::class)],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        return MovimientoResource::collection(
            $this->inventario->consultarKardex($insumo, $filtros, (int) ($filtros['por_pagina'] ?? 30))
        )->additional(['insumo' => new InsumoResource($insumo->refresh())]);
    }

    private function respuesta(Insumo $insumo, $movimiento, string $mensaje, int $codigo): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'data' => new MovimientoResource($movimiento->load('usuario:id_usuario,nombres')),
            'insumo' => new InsumoResource($insumo->refresh()),
        ], $codigo);
    }
}
