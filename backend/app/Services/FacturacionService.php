<?php

namespace App\Services;

use App\Contracts\Repositories\FacturaRepositoryInterface;
use App\DTOs\FacturaDTO;
use App\DTOs\PagoDTO;
use App\Enums\EstadoFactura;
use App\Enums\EstadoPedido;
use App\Enums\MetodoPago;
use App\Enums\TipoDescuento;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Facturacion\CalculadoraFactura;
use App\Facturacion\ResumenFactura;
use App\Models\DetallePedido;
use App\Models\Factura;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Módulo 6: RF28 Generar factura, RF29 Calcular impuestos, RF30 Aplicar
 * descuentos, RF31 Registrar pago y RF32 Emitir comprobante.
 *
 * Flujo: pedido "Entregado" → factura pendiente → pagos → factura pagada y
 * pedido "Facturado". Los valores los calcula CalculadoraFactura (Strategy).
 */
class FacturacionService
{
    public function __construct(
        private readonly FacturaRepositoryInterface $facturas,
        private readonly CalculadoraFactura $calculadora,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return $this->facturas->buscar($filtros, $porPagina);
    }

    /** Pedidos entregados esperando factura (la lista de trabajo del cajero). */
    public function porCobrar(): Collection
    {
        return $this->facturas->pedidosPorFacturar();
    }

    /** RF29 y RF30: calcula la cuenta sin guardar nada (para ver el total antes de facturar). */
    public function previsualizar(FacturaDTO $dto): ResumenFactura
    {
        $pedido = $this->pedidoFacturable($dto->idPedido, bloquear: false);

        return $this->calcular($pedido, $dto->descuentoTipo, $dto->descuentoValor, $dto->motivoDescuento, exigirMotivo: false);
    }

    /** RF28. Copia las líneas del pedido y fija los valores calculados. */
    public function generar(FacturaDTO $dto, Usuario $cajero): Factura
    {
        $factura = DB::transaction(function () use ($dto, $cajero) {
            $pedido = $this->pedidoFacturable($dto->idPedido, bloquear: true);
            $resumen = $this->calcular($pedido, $dto->descuentoTipo, $dto->descuentoValor, $dto->motivoDescuento);

            $factura = $this->facturas->crear([
                'id_pedido' => $pedido->id_pedido,
                'id_usuario' => $cajero->id_usuario,
                'cliente_nombre' => $dto->clienteNombre,
                'cliente_documento' => $dto->clienteDocumento,
                'motivo_descuento' => $resumen->descuento > 0 ? $dto->motivoDescuento : null,
                'fecha_emision' => now(),
                ...$resumen->aArray(),
            ]);

            $this->facturas->actualizar($factura, ['numero' => $this->numeroPara($factura)]);
            $this->facturas->agregarLineas($factura, $this->lineasDe($pedido));

            return $factura;
        });

        EventoAuditable::dispatch(
            TipoEvento::FACTURA_GENERADA,
            "Se generó la factura {$factura->numero} del pedido #{$factura->id_pedido} por {$this->pesos($factura->total)}.",
        );

        return $this->facturas->cargarDetalle($factura);
    }

    /** RF30. Solo mientras la factura está pendiente y sin pagos (el total cambia). */
    public function aplicarDescuento(Factura $factura, TipoDescuento $tipo, float $valor, ?string $motivo): Factura
    {
        $factura = DB::transaction(function () use ($factura, $tipo, $valor, $motivo) {
            $factura = $this->facturas->bloquear($factura);
            $this->verificarPendiente($factura, 'cambiar el descuento de');

            if ($factura->tienePagos()) {
                throw new ReglaNegocioException(
                    "La factura {$factura->numero} ya tiene pagos; no se puede cambiar el descuento.",
                    409,
                    'factura_con_pagos',
                );
            }

            $pedido = $factura->pedido()->with('detalles')->firstOrFail();
            $resumen = $this->calcular($pedido, $tipo, $valor, $motivo);

            return $this->facturas->actualizar($factura, [
                'motivo_descuento' => $resumen->descuento > 0 ? $motivo : null,
                ...$resumen->aArray(),
            ]);
        });

        $texto = $factura->descuento > 0
            ? "se aplicó un descuento de {$this->pesos($factura->descuento)} (total {$this->pesos($factura->total)})"
            : 'se quitó el descuento';
        EventoAuditable::dispatch(TipoEvento::FACTURA_DESCUENTO_APLICADO, "Factura {$factura->numero}: {$texto}.");

        return $this->facturas->cargarDetalle($factura);
    }

    /**
     * RF31. Admite pagos divididos: cuando los pagos cubren el total, la
     * factura queda pagada y el pedido pasa a "Facturado".
     */
    public function registrarPago(Factura $factura, PagoDTO $dto, Usuario $cajero): Factura
    {
        [$factura, $pago, $quedoPagada] = DB::transaction(function () use ($factura, $dto, $cajero) {
            $factura = $this->facturas->bloquear($factura);
            $this->verificarPendiente($factura, 'registrar pagos en');

            $saldo = $factura->saldo();
            $this->validarPago($dto, $saldo);

            $recibido = $dto->metodo === MetodoPago::EFECTIVO ? ($dto->montoRecibido ?? $dto->monto) : null;

            $pago = $this->facturas->registrarPago($factura, [
                'metodo' => $dto->metodo,
                'monto' => $dto->monto,
                'monto_recibido' => $recibido,
                'cambio' => $recibido !== null ? round($recibido - $dto->monto, 2) : 0,
                'referencia' => $dto->metodo->requiereReferencia() ? $dto->referencia : null,
                'id_usuario' => $cajero->id_usuario,
                'fecha' => now(),
            ]);

            $factura->unsetRelation('pagos');
            $quedoPagada = $factura->saldo() <= 0;

            if ($quedoPagada) {
                $this->facturas->actualizar($factura, ['estado' => EstadoFactura::PAGADA, 'fecha_pago' => now()]);

                $pedido = $factura->pedido()->firstOrFail();
                $pedido->forceFill(['estado_pago' => 'pagado']);
                $pedido->marcarFacturado();
            }

            return [$factura, $pago, $quedoPagada];
        });

        EventoAuditable::dispatch(
            TipoEvento::FACTURA_PAGO_REGISTRADO,
            "Factura {$factura->numero}: pago de {$this->pesos($pago->monto)} en {$pago->metodo->etiqueta()}.",
        );

        if ($quedoPagada) {
            EventoAuditable::dispatch(
                TipoEvento::FACTURA_PAGADA,
                "La factura {$factura->numero} quedó pagada; el pedido #{$factura->id_pedido} pasó a Facturado.",
            );
        }

        return $this->facturas->cargarDetalle($factura);
    }

    /** Anula una factura pendiente sin pagos; el pedido sigue "Entregado" y se puede facturar de nuevo. */
    public function anular(Factura $factura, string $motivo): Factura
    {
        $factura = DB::transaction(function () use ($factura, $motivo) {
            $factura = $this->facturas->bloquear($factura);
            $this->verificarPendiente($factura, 'anular');

            if ($factura->tienePagos()) {
                throw new ReglaNegocioException(
                    "La factura {$factura->numero} ya tiene pagos y no se puede anular.",
                    409,
                    'factura_con_pagos',
                );
            }

            return $this->facturas->actualizar($factura, [
                'estado' => EstadoFactura::ANULADA,
                'motivo_anulacion' => $motivo,
                'fecha_anulacion' => now(),
            ]);
        });

        EventoAuditable::dispatch(TipoEvento::FACTURA_ANULADA, "Se anuló la factura {$factura->numero}. Motivo: {$motivo}.");

        return $this->facturas->cargarDetalle($factura);
    }

    /**
     * RF32. Registra la emisión (la primera vez, en una factura pagada) y
     * devuelve la factura completa para armar el comprobante. Se puede
     * volver a emitir (reimpresión).
     */
    public function emitirComprobante(Factura $factura): Factura
    {
        if (! $factura->estado->esVigente()) {
            throw new ReglaNegocioException("La factura {$factura->numero} está anulada; no tiene comprobante.", 409);
        }

        $primeraVez = $factura->comprobante_emitido_en === null;

        if ($primeraVez && $factura->estado === EstadoFactura::PAGADA) {
            $this->facturas->actualizar($factura, ['comprobante_emitido_en' => now()]);
        }

        EventoAuditable::dispatch(
            TipoEvento::COMPROBANTE_EMITIDO,
            $primeraVez
                ? "Se emitió el comprobante de la factura {$factura->numero}."
                : "Se reimprimió el comprobante de la factura {$factura->numero}.",
        );

        return $this->facturas->cargarDetalle($factura);
    }

    // --- Apoyo ----------------------------------------------------------------

    /** El pedido debe estar entregado, tener productos y no tener una factura vigente. */
    private function pedidoFacturable(int $idPedido, bool $bloquear): Pedido
    {
        $pedido = $bloquear
            ? $this->facturas->pedidoParaFacturar($idPedido)
            : Pedido::query()->find($idPedido);

        if (! $pedido) {
            throw ValidationException::withMessages(['id_pedido' => 'El pedido no existe.']);
        }

        if ($pedido->estado !== EstadoPedido::ENTREGADO) {
            $mensaje = $pedido->estado === EstadoPedido::FACTURADO
                ? "El pedido #{$pedido->id_pedido} ya está facturado."
                : "Solo se facturan pedidos entregados; el pedido #{$pedido->id_pedido} está \"{$pedido->estado->etiqueta()}\".";

            throw new ReglaNegocioException($mensaje, 409, 'pedido_no_facturable');
        }

        if ($vigente = $this->facturas->facturaVigenteDePedido($pedido->id_pedido)) {
            throw new ReglaNegocioException(
                "El pedido #{$pedido->id_pedido} ya tiene la factura {$vigente->numero}.",
                409,
                'pedido_ya_facturado',
            );
        }

        $pedido->load(['detalles.producto', 'detalles.adiciones', 'mesa']);

        if ($pedido->detalles->isEmpty()) {
            throw new ReglaNegocioException("El pedido #{$pedido->id_pedido} no tiene productos para facturar.", 409);
        }

        return $pedido;
    }

    /** RF29 y RF30. El descuento exige un motivo (queda en la factura y en la bitácora). */
    private function calcular(Pedido $pedido, TipoDescuento $tipo, float $valor, ?string $motivo, bool $exigirMotivo = true): ResumenFactura
    {
        $resumen = $this->calculadora->calcular($pedido, $tipo, $valor);

        if ($exigirMotivo && $resumen->descuento > 0 && blank($motivo)) {
            throw ValidationException::withMessages(['motivo_descuento' => 'Escribe el motivo del descuento.']);
        }

        if ($resumen->total <= 0) {
            throw ValidationException::withMessages(['descuento_valor' => 'El total de la factura debe ser mayor que cero.']);
        }

        return $resumen;
    }

    private function verificarPendiente(Factura $factura, string $accion): void
    {
        if (! $factura->estaPendiente()) {
            throw new ReglaNegocioException(
                "No se puede {$accion} la factura {$factura->numero}: está {$this->minuscula($factura->estado->etiqueta())}.",
                409,
                'factura_no_pendiente',
            );
        }
    }

    /** @throws ValidationException si el pago no es válido para el saldo actual */
    private function validarPago(PagoDTO $dto, float $saldo): void
    {
        if ($dto->monto <= 0) {
            throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor que cero.']);
        }

        if ($dto->monto > $saldo) {
            throw ValidationException::withMessages([
                'monto' => "El pago supera el saldo de la factura ({$this->pesos($saldo)}).",
            ]);
        }

        if ($dto->metodo->requiereReferencia() && blank($dto->referencia)) {
            throw ValidationException::withMessages([
                'referencia' => 'Escribe el número de aprobación o de la transferencia.',
            ]);
        }

        if ($dto->metodo === MetodoPago::EFECTIVO && $dto->montoRecibido !== null && $dto->montoRecibido < $dto->monto) {
            throw ValidationException::withMessages([
                'monto_recibido' => 'El efectivo recibido no alcanza para el monto a pagar.',
            ]);
        }
    }

    /** Consecutivo legible a partir del id: FAC-000001. */
    private function numeroPara(Factura $factura): string
    {
        return config('siar.facturacion.prefijo').'-'.str_pad((string) $factura->id_factura, 6, '0', STR_PAD_LEFT);
    }

    /** @return list<array<string, mixed>> líneas de factura copiadas del pedido */
    private function lineasDe(Pedido $pedido): array
    {
        return $pedido->detalles->map(function (DetallePedido $detalle) {
            $descuento = (float) ($detalle->descuento ?? 0);
            $adiciones = $detalle->adiciones->pluck('nombre')->implode(', ');

            return [
                'id_producto' => $detalle->id_producto,
                'descripcion' => $detalle->nombre_producto ?: ($detalle->producto?->nombre ?? 'Producto'),
                'adiciones' => $adiciones === '' ? null : mb_substr($adiciones, 0, 255),
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => $detalle->precio_unitario,
                'descuento' => $descuento,
                'subtotal' => round($detalle->subtotal() - $descuento, 2),
            ];
        })->values()->all();
    }

    private function pesos(float|string $valor): string
    {
        return '$ '.number_format((float) $valor, 0, ',', '.');
    }

    private function minuscula(string $texto): string
    {
        return mb_strtolower($texto);
    }
}
