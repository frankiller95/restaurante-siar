<?php

namespace App\Contracts\Repositories;

use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductoRepositoryInterface
{
    /**
     * Filtro "estado": "activos" (por defecto), "baja" o "todos".
     *
     * @param  array{buscar?: string|null, id_categoria?: int|null, disponible?: bool|null, estado?: string|null}  $filtros
     */
    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator;

    public function obtenerPorId(int $idProducto): ?Producto;

    /** CU01 flujo 3a: ¿ya hay un producto activo con ese nombre en la categoría? */
    public function existeNombreEnCategoria(string $nombre, int $idCategoria, ?int $exceptoId = null): bool;

    /** CU03 paso 4: ¿tiene pedidos pendientes o en preparación? */
    public function tienePedidosPendientes(Producto $producto): bool;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Producto;

    /** @param array<string, mixed> $datos */
    public function actualizar(Producto $producto, array $datos): Producto;
}
