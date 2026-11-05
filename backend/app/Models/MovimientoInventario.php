<?php

namespace App\Models;

use App\Enums\TipoMovimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Movimiento del kárdex (RF25, RF26, RF27, RF39). La cantidad lleva signo:
 * positiva si entra al inventario, negativa si sale.
 *
 * @property int $id_movimiento
 * @property int $id_insumo
 * @property TipoMovimiento $tipo
 * @property string $cantidad
 * @property string $stock_resultante
 * @property string|null $costo_unitario
 * @property string|null $proveedor
 * @property string|null $motivo
 * @property Carbon $fecha
 * @property int|null $id_usuario
 * @property int|null $id_pedido
 */
class MovimientoInventario extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = null;

    protected $table = 'movimientos_inventario';

    protected $primaryKey = 'id_movimiento';

    protected $fillable = [
        'id_insumo',
        'tipo',
        'cantidad',
        'stock_resultante',
        'costo_unitario',
        'proveedor',
        'motivo',
        'fecha',
        'id_usuario',
        'id_pedido',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimiento::class,
            'cantidad' => 'decimal:3',
            'stock_resultante' => 'decimal:3',
            'costo_unitario' => 'decimal:2',
            'fecha' => 'datetime',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'id_insumo', 'id_insumo');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function esEntrada(): bool
    {
        return (float) $this->cantidad > 0;
    }

    public function calcularCostoTotal(): float
    {
        return round(abs((float) $this->cantidad) * (float) ($this->costo_unitario ?? 0), 2);
    }
}
