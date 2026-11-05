<?php

namespace App\Models;

use App\Models\Concerns\TieneBajaLogica;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Producto del menú: plato o bebida (RF07 a RF11).
 *
 * @property int $id_producto
 * @property string $nombre
 * @property string|null $descripcion
 * @property string $precio
 * @property string|null $imagen_url ruta relativa de la imagen en el disco "imagenes"
 * @property bool $disponible
 * @property bool $activo
 * @property Carbon|null $fecha_creacion
 * @property Carbon|null $fecha_modificacion
 * @property Carbon|null $fecha_baja
 * @property int $id_categoria
 * @property-read Categoria $categoria
 */
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory, TieneBajaLogica;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_modificacion';

    protected $table = 'productos';

    protected $primaryKey = 'id_producto';

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'imagen_url',
        'disponible',
        'id_categoria',
    ];

    protected $attributes = [
        'disponible' => true,
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'disponible' => 'boolean',
            'agotado_por_inventario' => 'boolean',
            'activo' => 'boolean',
            'fecha_creacion' => 'datetime',
            'fecha_modificacion' => 'datetime',
            'fecha_baja' => 'datetime',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function cambiarCategoria(Categoria $categoria): void
    {
        $this->categoria()->associate($categoria)->save();
    }

    /** Receta del producto (RF38, Módulo 5). */
    public function receta(): HasMany
    {
        return $this->hasMany(DetalleReceta::class, 'id_producto', 'id_producto');
    }

    /**
     * CU05: cambio manual (cocinero o administrador). Quita la marca del
     * inventario, para que una reposición no pise la decisión manual.
     */
    public function marcarDisponible(bool $disponible): void
    {
        $this->forceFill(['disponible' => $disponible, 'agotado_por_inventario' => false])->save();
    }

    /** RF40: el inventario agota (true) o repone (false) el producto. */
    public function marcarAgotadoPorInventario(bool $agotado): void
    {
        $this->forceFill(['disponible' => ! $agotado, 'agotado_por_inventario' => $agotado])->save();
    }

    /**
     * Porciones de la receta (RF38). Sin receta, devuelve una lista vacía.
     *
     * @return Collection<int, DetalleReceta>
     */
    public function insumosNecesarios(int $porciones = 1): Collection
    {
        return $this->receta->map(fn (DetalleReceta $linea) => $linea->setAttribute('cantidad_total', $linea->cantidadPara($porciones)));
    }

    /** ¿Se puede pedir? Activo, disponible y en una categoría activa. */
    public function estaEnMenu(): bool
    {
        return $this->activo && $this->disponible && (bool) $this->categoria?->activa;
    }

    /** Productos que aparecen en la carta (activos y de categorías activas). */
    public function scopeEnCarta(Builder $consulta): Builder
    {
        return $consulta->activos()->whereHas('categoria', fn ($q) => $q->where('activa', true));
    }
}
