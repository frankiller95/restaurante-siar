<?php

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pago aplicado a una factura (RF31).
 *
 * @property int $id_pago
 * @property int $id_factura
 * @property MetodoPago $metodo
 * @property string $monto
 * @property string|null $monto_recibido
 * @property string $cambio
 * @property string|null $referencia
 * @property int|null $id_usuario
 * @property Carbon $fecha
 */
class Pago extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = null;

    protected $table = 'pagos';

    protected $primaryKey = 'id_pago';

    protected $fillable = [
        'id_factura', 'metodo', 'monto', 'monto_recibido', 'cambio', 'referencia', 'id_usuario', 'fecha',
    ];

    protected function casts(): array
    {
        return [
            'metodo' => MetodoPago::class,
            'monto' => 'decimal:2',
            'monto_recibido' => 'decimal:2',
            'cambio' => 'decimal:2',
            'fecha' => 'datetime',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'id_factura', 'id_factura');
    }

    /** Cajero que registró el pago. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
