<?php

namespace App\Contracts\Repositories;

use App\Models\Insumo;
use App\Models\MovimientoInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InsumoRepositoryInterface
{
    /**
     * Filtro "estado": "activos" (por defecto), "baja" o "todos".
     *
     * @param  array{buscar?: string|null, estado?: string|null, bajo_minimo?: bool}  $filtros
     */
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator;

    public function obtenerPorId(int $idInsumo): ?Insumo;

    public function existeNombre(string $nombre, ?int $exceptoId = null): bool;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Insumo;

    /** @param array<string, mixed> $datos */
    public function actualizar(Insumo $insumo, array $datos): Insumo;

    public function eliminar(Insumo $insumo): void;

    /** @param array<string, mixed> $datos */
    public function registrarMovimiento(array $datos): MovimientoInventario;

    /**
     * RF27 kárdex de un insumo.
     *
     * @param  array{desde?: string|null, hasta?: string|null, tipo?: string|null}  $filtros
     */
    public function movimientos(Insumo $insumo, array $filtros, int $porPagina = 30): LengthAwarePaginator;

    /** @return Collection<int, Insumo> activos en su stock mínimo o por debajo */
    public function bajoMinimo(): Collection;

    /**
     * RF39: cantidad neta consumida por un pedido, por insumo (consumos menos reintegros).
     *
     * @return array<int, float> id_insumo => cantidad (positiva)
     */
    public function consumoNetoDePedido(int $idPedido): array;

    /** ¿Lo usa la receta de algún producto activo? */
    public function usadoEnRecetasActivas(Insumo $insumo): bool;
}
