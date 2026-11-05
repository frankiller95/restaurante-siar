<?php

namespace App\Models;

use App\Enums\UnidadMedida;
use App\Models\Concerns\TieneBajaLogica;
use Database\Factories\InsumoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Insumo del inventario: ingrediente o material (RF23, RF24).
 *
 * @property int $id_insumo
 * @property string $nombre
 * @property UnidadMedida $unidad_medida
 * @property string $stock_actual
 * @property string $stock_minimo
 * @property string $costo_unitario
 * @property bool $activo
 */
class Insumo extends Model
{
    /** @use HasFactory<InsumoFactory> */
    use HasFactory, TieneBajaLogica;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_modificacion';

    protected $table = 'insumos';

    protected $primaryKey = 'id_insumo';

    protected $fillable = ['nombre', 'unidad_medida', 'stock_minimo', 'costo_unitario'];

    protected $attributes = [
        'stock_actual' => 0,
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'unidad_medida' => UnidadMedida::class,
            'stock_actual' => 'decimal:3',
            'stock_minimo' => 'decimal:3',
            'costo_unitario' => 'decimal:2',
            'activo' => 'boolean',
            'fecha_creacion' => 'datetime',
            'fecha_modificacion' => 'datetime',
            'fecha_baja' => 'datetime',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'id_insumo', 'id_insumo');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(DetalleReceta::class, 'id_insumo', 'id_insumo');
    }

    /** Suma (o resta, con cantidad negativa) al stock y devuelve el stock nuevo. */
    public function moverStock(float $cantidad): float
    {
        $nuevo = round((float) $this->stock_actual + $cantidad, 3);
        $this->forceFill(['stock_actual' => $nuevo])->save();

        return $nuevo;
    }

    public function estaBajoMinimo(): bool
    {
        return (float) $this->stock_actual <= (float) $this->stock_minimo;
    }

    public function alcanzaPara(float $cantidad): bool
    {
        return (float) $this->stock_actual >= $cantidad;
    }

    public function tieneHistorial(): bool
    {
        return $this->movimientos()->exists() || $this->recetas()->exists();
    }
}
