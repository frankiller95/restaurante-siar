<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Baja lógica (CU03): el registro no se borra, se marca como inactivo y se
 * guarda la fecha de la baja, para conservar el historial (pedidos, reportes).
 *
 * El modelo debe tener las columnas `activo` (boolean) y `fecha_baja` (timestamp).
 * Se reutiliza en otros módulos, por ejemplo los insumos del inventario (RF24).
 */
trait TieneBajaLogica
{
    public function darDeBaja(): void
    {
        $this->forceFill([
            'activo' => false,
            'fecha_baja' => now(),
        ])->save();
    }

    public function estaActivo(): bool
    {
        return (bool) $this->activo;
    }

    /** Solo los registros que no se han dado de baja. */
    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where($this->qualifyColumn('activo'), true);
    }

    /** Solo los registros dados de baja. */
    public function scopeDadosDeBaja(Builder $consulta): Builder
    {
        return $consulta->where($this->qualifyColumn('activo'), false);
    }
}
