<?php

namespace App\Contracts\Repositories;

use App\Models\DetallePedido;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PedidoRepositoryInterface
{
    /**
     * Filtro "estado": un estado concreto o "abiertos" (ni facturados ni cancelados).
     * Filtro "fecha": día de creación (Y-m-d) en la zona horaria del restaurante.
     *
     * @param  array{estado?: string|null, id_mesa?: int|null, fecha?: string|null, id_usuario?: int|null}  $filtros
     */
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator;

    /** @return Collection<int, Pedido> enviados a cocina y aún no entregados, en orden de llegada */
    public function colaDeCocina(): Collection;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Pedido;

    /** @param array<string, mixed> $datos */
    public function agregarLinea(Pedido $pedido, array $datos): DetallePedido;

    /** Línea del mismo producto, observación y precio (para sumar cantidad). */
    public function lineaIgual(Pedido $pedido, int $idProducto, ?string $observacion, string $precio): ?DetallePedido;

    /** Carga mesa, mesero y productos para responder. */
    public function cargarDetalle(Pedido $pedido): Pedido;
}
