<?php

namespace App\Contracts\Repositories;

use App\Models\Mesa;
use Illuminate\Support\Collection;

interface MesaRepositoryInterface
{
    /**
     * @param  array{estado?: string|null, ubicacion?: string|null, incluir_baja?: bool}  $filtros
     * @return Collection<int, Mesa> mesas con su próxima reserva, ordenadas por número
     */
    public function buscar(array $filtros = []): Collection;

    /** @return array<string, int> cantidad de mesas activas por estado */
    public function resumenPorEstado(): array;

    public function obtenerPorId(int $idMesa): ?Mesa;

    public function existeNumero(int $numero, ?int $exceptoId = null): bool;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Mesa;

    /** @param array<string, mixed> $datos */
    public function actualizar(Mesa $mesa, array $datos): Mesa;

    /** RF16: ¿tiene pedidos abiertos? (Módulo 4) */
    public function tienePedidosAbiertos(Mesa $mesa): bool;

    /** @return Collection<int, Mesa> mesas activas, fuera de mantenimiento y con capacidad suficiente */
    public function candidatasParaReserva(int $personas): Collection;
}
