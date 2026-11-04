<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CategoriaRepositoryInterface;
use App\Models\Categoria;
use Illuminate\Support\Collection;

class CategoriaRepository implements CategoriaRepositoryInterface
{
    public function todas(bool $soloActivas = false): Collection
    {
        return Categoria::query()
            ->withCount(['productos' => fn ($q) => $q->where('activo', true)])
            ->when($soloActivas, fn ($q) => $q->where('activa', true))
            ->orderBy('nombre')
            ->get();
    }

    public function obtenerPorId(int $idCategoria): ?Categoria
    {
        return Categoria::find($idCategoria);
    }

    public function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        return Categoria::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($exceptoId, fn ($q) => $q->where('id_categoria', '!=', $exceptoId))
            ->exists();
    }

    public function crear(array $datos): Categoria
    {
        return Categoria::create($datos)->loadCount(['productos' => fn ($q) => $q->where('activo', true)]);
    }

    public function actualizar(Categoria $categoria, array $datos): Categoria
    {
        $categoria->fill($datos)->save();

        return $categoria->loadCount(['productos' => fn ($q) => $q->where('activo', true)]);
    }

    public function eliminar(Categoria $categoria): void
    {
        $categoria->delete();
    }

    public function conProductosEnCarta(): Collection
    {
        return Categoria::query()
            ->activas()
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
            ->with(['productos' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();
    }
}
