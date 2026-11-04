<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría del menú: entradas, platos fuertes, bebidas, postres... (RF10).
 *
 * @property int $id_categoria
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool $activa
 */
class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected $table = 'categorias';

    protected $primaryKey = 'id_categoria';

    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_categoria', 'id_categoria');
    }

    public function activar(): void
    {
        $this->forceFill(['activa' => true])->save();
    }

    public function desactivar(): void
    {
        $this->forceFill(['activa' => false])->save();
    }

    /** ¿Tiene productos (incluidos los dados de baja, que siguen en el historial)? */
    public function tieneProductos(): bool
    {
        return $this->productos()->exists();
    }

    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('activa', true);
    }
}
