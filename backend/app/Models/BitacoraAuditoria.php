<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id_bitacora
 * @property string $tipo_evento
 * @property string $descripcion
 * @property Carbon $fecha_hora
 * @property string|null $ip_origen
 * @property int|null $id_usuario
 */
class BitacoraAuditoria extends Model
{
    protected $table = 'bitacora_auditoria';

    protected $primaryKey = 'id_bitacora';

    public $timestamps = false;

    protected $fillable = ['tipo_evento', 'descripcion', 'fecha_hora', 'ip_origen', 'id_usuario'];

    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
