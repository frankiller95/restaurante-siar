<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id_rol
 * @property string $nombre_rol
 * @property string|null $descripcion
 * @property bool $es_predefinido
 */
class Rol extends Model
{
    use HasFactory;

    public const ADMINISTRADOR = 'Administrador';

    public const CAJERO = 'Cajero';

    public const MESERO = 'Mesero';

    public const COCINERO = 'Cocinero';

    /** Roles del sistema: no se renombran ni se eliminan, solo se configuran. */
    public const PREDEFINIDOS = [self::ADMINISTRADOR, self::CAJERO, self::MESERO, self::COCINERO];

    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    protected $fillable = ['nombre_rol', 'descripcion', 'es_predefinido'];

    protected function casts(): array
    {
        return ['es_predefinido' => 'boolean'];
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos', 'id_rol', 'id_permiso');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_rol', 'id_rol');
    }

    public function esAdministrador(): bool
    {
        return $this->es_predefinido && $this->nombre_rol === self::ADMINISTRADOR;
    }

    /** Solo se elimina un rol personalizado sin usuarios asociados. */
    public function esEliminable(): bool
    {
        return ! $this->es_predefinido && $this->usuarios()->doesntExist();
    }
}
