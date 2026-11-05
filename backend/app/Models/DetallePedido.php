<?php

namespace App\Models;

use Database\Factories\DetallePedidoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de un pedido (RF18). precio_unitario es el precio del producto al
 * momento de pedirlo (no cambia si después cambia el precio del menú).
 *
 * @property int $id_detalle
 * @property int $id_pedido
 * @property int $id_producto
 * @property int $cantidad
 * @property string $precio_unitario
 * @property string|null $observacion
 * @property-read Producto $producto
 * @property-read Pedido $pedido
 */
class DetallePedido extends Model
{
    /** @use HasFactory<DetallePedidoFactory> */
    use HasFactory;

    protected $table = 'detalle_pedidos';

    protected $primaryKey = 'id_detalle';

    public $timestamps = false;

    protected $fillable = ['id_pedido', 'id_producto', 'cantidad', 'precio_unitario', 'observacion'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function subtotal(): float
    {
        return round($this->cantidad * (float) $this->precio_unitario, 2);
    }
}
