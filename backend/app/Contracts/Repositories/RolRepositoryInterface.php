<?php

namespace App\Contracts\Repositories;

use App\Models\Rol;
use Illuminate\Support\Collection;

interface RolRepositoryInterface
{
    /** @return Collection<int, Rol> roles con sus permisos y el conteo de usuarios */
    public function todos(): Collection;

    public function obtenerPorId(int $idRol): ?Rol;

    public function existeNombre(string $nombreRol, ?int $exceptoId = null): bool;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Rol;

    /** @param array<string, mixed> $datos */
    public function actualizar(Rol $rol, array $datos): Rol;

    public function eliminar(Rol $rol): void;

    /**
     * Reemplaza los permisos del rol.
     *
     * @param  list<int>  $idsPermiso
     * @return bool true si los permisos cambiaron
     */
    public function sincronizarPermisos(Rol $rol, array $idsPermiso): bool;
}
