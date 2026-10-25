<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PermisoRepositoryInterface;
use App\Models\Permiso;
use Illuminate\Support\Collection;

class PermisoRepository implements PermisoRepositoryInterface
{
    public function todos(): Collection
    {
        return Permiso::orderBy('id_permiso')->get();
    }

    public function codigosPorIds(array $idsPermiso): array
    {
        return Permiso::whereIn('id_permiso', $idsPermiso)->pluck('codigo_permiso')->all();
    }
}
