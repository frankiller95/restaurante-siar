<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una factura (RF28): copia de la línea del pedido al facturar.
 *
 * @property int $id_detalle_factura
 * @property int $id_factura
 * @property int|null $id_producto
 * @property string $descripcion
 * @property string|null $adiciones
 * @property int $cantidad
 * @property string $precio_unitario
 * @property string $descuento
 * @property string $subtotal
 */
class DetalleFactura extends Model
{
    protected $table = 'detalle_facturas';

    protected $primaryKey = 'id_detalle_factura';

    public $timestamps = false;

    protected $fillable = [
        'id_factura', 'id_producto', 'descripcion', 'adiciones', 'cantidad',
        'precio_unitario', 'descuento', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'id_factura', 'id_factura');
    }
}
