<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\RolRepositoryInterface;
use App\Models\Rol;
use Illuminate\Support\Collection;

class RolRepository implements RolRepositoryInterface
{
    public function todos(): Collection
    {
        return Rol::with('permisos')
            ->withCount('usuarios')
            ->orderByDesc('es_predefinido')
            ->orderBy('nombre_rol')
            ->get();
    }

    public function obtenerPorId(int $idRol): ?Rol
    {
        return Rol::with('permisos')->withCount('usuarios')->find($idRol);
    }

    public function existeNombre(string $nombreRol, ?int $exceptoId = null): bool
    {
        return Rol::whereRaw('LOWER(nombre_rol) = ?', [mb_strtolower($nombreRol)])
            ->when($exceptoId, fn ($q) => $q->where('id_rol', '!=', $exceptoId))
            ->exists();
    }

    public function crear(array $datos): Rol
    {
        return Rol::create($datos)->load('permisos')->loadCount('usuarios');
    }

    public function actualizar(Rol $rol, array $datos): Rol
    {
        $rol->fill($datos)->save();

        return $rol->load('permisos')->loadCount('usuarios');
    }

    public function eliminar(Rol $rol): void
    {
        $rol->delete();
    }

    public function sincronizarPermisos(Rol $rol, array $idsPermiso): bool
    {
        $cambios = $rol->permisos()->sync($idsPermiso);
        $rol->load('permisos');

        return $cambios['attached'] !== [] || $cambios['detached'] !== [];
    }
}
