<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id_permiso
 * @property string $codigo_permiso
 * @property string $nombre
 * @property string|null $descripcion
 */
class Permiso extends Model
{
    protected $table = 'permisos';

    protected $primaryKey = 'id_permiso';

    public $timestamps = false;

    protected $fillable = ['codigo_permiso', 'nombre', 'descripcion'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permisos', 'id_permiso', 'id_rol');
    }
}
