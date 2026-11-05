<?php

namespace App\Http\Controllers\Api;

use App\DTOs\LineaPedidoDTO;
use App\DTOs\PedidoDTO;
use App\Enums\EstadoPedido;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pedido\AgregarProductoRequest;
use App\Http\Requests\Pedido\CrearPedidoRequest;
use App\Http\Requests\Pedido\ModificarProductoRequest;
use App\Http\Resources\PedidoResource;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * RF17 a RF22 (Módulo 4: Pedidos).
 */
class PedidoController extends Controller
{
    public function __construct(private readonly PedidoService $pedidos) {}

    /** RF22 Consultar estado. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['abiertos', ...array_map(fn ($e) => $e->value, EstadoPedido::cases())])],
            'id_mesa' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'mios' => ['nullable', 'boolean'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        if ($request->boolean('mios')) {
            $filtros['id_usuario'] = $request->user()->id_usuario;
        }

        return PedidoResource::collection($this->pedidos->listar($filtros, (int) ($filtros['por_pagina'] ?? 20)));
    }

    /** RF22: cola de la cocina. */
    public function cocina(): AnonymousResourceCollection
    {
        return PedidoResource::collection($this->pedidos->colaDeCocina());
    }

    public function show(Pedido $pedido): PedidoResource
    {
        return new PedidoResource($pedido->load(['mesa', 'usuario:id_usuario,nombres', 'detalles.producto']));
    }

    /** RF17 Crear pedido (con o sin productos iniciales). */
    public function store(CrearPedidoRequest $request): JsonResponse
    {
        $pedido = $this->pedidos->crear(PedidoDTO::desdeArray($request->validated()), $request->user());

        return $this->respuesta($pedido, "Pedido #{$pedido->id_pedido} creado para la mesa {$pedido->mesa->numero}.", 201);
    }

    /** RF19: observaciones generales. */
    public function update(Request $request, Pedido $pedido): JsonResponse
    {
        $datos = $request->validate(['observaciones' => ['nullable', 'string', 'max:255']]);

        return $this->respuesta($this->pedidos->actualizar($pedido, $datos['observaciones'] ?? null), 'Pedido actualizado.');
    }

    /** RF18 Agregar productos. */
    public function agregarProducto(AgregarProductoRequest $request, Pedido $pedido): JsonResponse
    {
        $pedido = $this->pedidos->agregarProducto($pedido, LineaPedidoDTO::desdeArray($request->validated()));

        return $this->respuesta($pedido, 'Producto agregado al pedido.');
    }

    /** RF19 Modificar una línea. */
    public function modificarProducto(ModificarProductoRequest $request, Pedido $pedido, DetallePedido $detalle): JsonResponse
    {
        $observacion = trim((string) $request->validated('observacion'));
        $pedido = $this->pedidos->modificarProducto(
            $pedido,
            $detalle,
            (int) $request->validated('cantidad'),
            $observacion === '' ? null : $observacion,
        );

        return $this->respuesta($pedido, 'Producto actualizado.');
    }

    /** RF19 Quitar una línea. */
    public function quitarProducto(Pedido $pedido, DetallePedido $detalle): JsonResponse
    {
        return $this->respuesta($this->pedidos->quitarProducto($pedido, $detalle), 'Producto quitado del pedido.');
    }

    /** RF21 Enviar a cocina. */
    public function enviarACocina(Pedido $pedido): JsonResponse
    {
        return $this->respuesta($this->pedidos->enviarACocina($pedido), "Pedido #{$pedido->id_pedido} enviado a cocina.");
    }

    public function preparar(Pedido $pedido): JsonResponse
    {
        return $this->respuesta($this->pedidos->iniciarPreparacion($pedido), "Pedido #{$pedido->id_pedido} en preparación.");
    }

    public function listo(Pedido $pedido): JsonResponse
    {
        return $this->respuesta($this->pedidos->marcarListo($pedido), "Pedido #{$pedido->id_pedido} listo para entregar.");
    }

    public function entregar(Pedido $pedido): JsonResponse
    {
        return $this->respuesta($this->pedidos->entregar($pedido), "Pedido #{$pedido->id_pedido} entregado.");
    }

    /** RF20 Cancelar pedido. */
    public function cancelar(Request $request, Pedido $pedido): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);
        $motivo = trim((string) ($datos['motivo'] ?? ''));

        return $this->respuesta(
            $this->pedidos->cancelar($pedido, $motivo === '' ? null : $motivo),
            "Pedido #{$pedido->id_pedido} cancelado.",
        );
    }

    private function respuesta(Pedido $pedido, string $mensaje, int $codigo = 200): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'data' => new PedidoResource($pedido)], $codigo);
    }
}
