<?php

namespace App\Contracts\Repositories;

use App\Models\Permiso;
use Illuminate\Support\Collection;

interface PermisoRepositoryInterface
{
    /** @return Collection<int, Permiso> */
    public function todos(): Collection;

    /**
     * @param  list<int>  $idsPermiso
     * @return list<string> códigos de los permisos indicados
     */
    public function codigosPorIds(array $idsPermiso): array;
}
