<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de la receta de un producto (RF38): cuánto de un insumo lleva una
 * porción, en la unidad de medida del insumo.
 *
 * @property int $id_detalle_receta
 * @property int $id_producto
 * @property int $id_insumo
 * @property string $cantidad_por_porcion
 * @property-read Insumo $insumo
 * @property-read Producto $producto
 */
class DetalleReceta extends Model
{
    protected $table = 'detalle_recetas';

    protected $primaryKey = 'id_detalle_receta';

    public $timestamps = false;

    protected $fillable = ['id_producto', 'id_insumo', 'cantidad_por_porcion'];

    protected function casts(): array
    {
        return ['cantidad_por_porcion' => 'decimal:3'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class, 'id_insumo', 'id_insumo');
    }

    public function cantidadPara(int $porciones): float
    {
        return round((float) $this->cantidad_por_porcion * $porciones, 3);
    }
}
