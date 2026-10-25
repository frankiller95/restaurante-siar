<?php

namespace App\Contracts\Repositories;

use App\Models\BitacoraAuditoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BitacoraRepositoryInterface
{
    /** @param array<string, mixed> $datos */
    public function registrar(array $datos): BitacoraAuditoria;

    /**
     * @param  array{tipo_evento?: string|null, buscar?: string|null, desde?: string|null, hasta?: string|null}  $filtros
     */
    public function buscar(array $filtros, int $porPagina = 20): LengthAwarePaginator;
}
