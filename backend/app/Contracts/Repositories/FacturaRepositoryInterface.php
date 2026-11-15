<?php

namespace App\Contracts\Repositories;

use App\Models\Factura;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface FacturaRepositoryInterface
{
    /**
     * Filtros: "estado" (pendiente, pagada o anulada), "buscar" (número o
     * cliente), "desde" y "hasta" (días Y-m-d de emisión, en la zona horaria
     * del restaurante).
     *
     * @param  array{estado?: string|null, buscar?: string|null, desde?: string|null, hasta?: string|null}  $filtros
     */
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator;

    /** Pedido entregado que todavía no tiene una factura vigente (para la caja). */
    public function pedidosPorFacturar(): Collection;

    /** Bloquea la fila del pedido hasta terminar la transacción (evita doble factura). */
    public function pedidoParaFacturar(int $idPedido): ?Pedido;

    public function facturaVigenteDePedido(int $idPedido): ?Factura;

    /** Bloquea la fila de la factura hasta terminar la transacción (evita pagos dobles). */
    public function bloquear(Factura $factura): Factura;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Factura;

    /** @param array<string, mixed> $datos */
    public function actualizar(Factura $factura, array $datos): Factura;

    /** @param list<array<string, mixed>> $lineas */
    public function agregarLineas(Factura $factura, array $lineas): void;

    /** @param array<string, mixed> $datos */
    public function registrarPago(Factura $factura, array $datos): Pago;

    /** Carga pedido, mesa, cajero, líneas y pagos para responder. */
    public function cargarDetalle(Factura $factura): Factura;
}
