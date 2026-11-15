<?php

namespace App\Http\Controllers\Api;

use App\DTOs\FacturaDTO;
use App\DTOs\PagoDTO;
use App\Enums\EstadoFactura;
use App\Enums\TipoDescuento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Factura\AplicarDescuentoRequest;
use App\Http\Requests\Factura\GenerarFacturaRequest;
use App\Http\Requests\Factura\RegistrarPagoRequest;
use App\Http\Resources\ComprobanteResource;
use App\Http\Resources\FacturaResource;
use App\Http\Resources\PedidoResource;
use App\Models\Factura;
use App\Services\FacturacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Módulo 6: RF28 a RF32 (facturación, impuestos, descuentos, pagos y comprobante).
 */
class FacturaController extends Controller
{
    public function __construct(private readonly FacturacionService $facturacion) {}

    /** Facturas emitidas, con filtros por estado, texto y fechas. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoFactura::class)],
            'buscar' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        return FacturaResource::collection($this->facturacion->listar($filtros, (int) ($filtros['por_pagina'] ?? 20)));
    }

    /** Pedidos entregados que aún no tienen factura. */
    public function porCobrar(): AnonymousResourceCollection
    {
        return PedidoResource::collection($this->facturacion->porCobrar());
    }

    /** RF29 y RF30: cuenta calculada, sin guardar. */
    public function previsualizar(GenerarFacturaRequest $request): JsonResponse
    {
        $resumen = $this->facturacion->previsualizar(FacturaDTO::desdeArray($request->validated()));

        return response()->json(['data' => $resumen->aArray()]);
    }

    /** RF28. */
    public function store(GenerarFacturaRequest $request): JsonResponse
    {
        $factura = $this->facturacion->generar(FacturaDTO::desdeArray($request->validated()), $request->user());

        return $this->respuesta($factura, "Factura {$factura->numero} generada.", 201);
    }

    public function show(Factura $factura): FacturaResource
    {
        return new FacturaResource($factura->load(['pedido.mesa', 'usuario:id_usuario,nombres', 'detalles', 'pagos.usuario:id_usuario,nombres']));
    }

    /** RF30. */
    public function descuento(AplicarDescuentoRequest $request, Factura $factura): JsonResponse
    {
        $datos = $request->validated();
        $factura = $this->facturacion->aplicarDescuento(
            $factura,
            TipoDescuento::from($datos['descuento_tipo']),
            (float) ($datos['descuento_valor'] ?? 0),
            filled($datos['motivo_descuento'] ?? null) ? trim($datos['motivo_descuento']) : null,
        );

        return $this->respuesta($factura, 'Descuento actualizado.');
    }

    /** RF31. */
    public function pagar(RegistrarPagoRequest $request, Factura $factura): JsonResponse
    {
        $factura = $this->facturacion->registrarPago($factura, PagoDTO::desdeArray($request->validated()), $request->user());

        return $this->respuesta(
            $factura,
            $factura->estado === EstadoFactura::PAGADA
                ? "Factura {$factura->numero} pagada. El pedido #{$factura->id_pedido} quedó facturado."
                : 'Pago registrado.',
            201,
        );
    }

    public function anular(Request $request, Factura $factura): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']]);

        return $this->respuesta($this->facturacion->anular($factura, trim($datos['motivo'])), "Factura {$factura->numero} anulada.");
    }

    /** RF32. */
    public function comprobante(Factura $factura): ComprobanteResource
    {
        return new ComprobanteResource($this->facturacion->emitirComprobante($factura));
    }

    private function respuesta(Factura $factura, string $mensaje, int $codigo = 200): JsonResponse
    {
        return response()->json(['message' => $mensaje, 'data' => new FacturaResource($factura)], $codigo);
    }
}
