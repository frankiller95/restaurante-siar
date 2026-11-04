<?php

namespace App\Contracts\Repositories;

use App\Models\Categoria;
use Illuminate\Support\Collection;

interface CategoriaRepositoryInterface
{
    /** @return Collection<int, Categoria> categorías con el conteo de productos activos */
    public function todas(bool $soloActivas = false): Collection;

    public function obtenerPorId(int $idCategoria): ?Categoria;

    public function existeNombre(string $nombre, ?int $exceptoId = null): bool;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Categoria;

    /** @param array<string, mixed> $datos */
    public function actualizar(Categoria $categoria, array $datos): Categoria;

    public function eliminar(Categoria $categoria): void;

    /**
     * Carta del menú: categorías activas con sus productos activos.
     *
     * @return Collection<int, Categoria>
     */
    public function conProductosEnCarta(): Collection;
}
