<?php

namespace App\Models;

use App\Enums\EstadoReserva;
use Database\Factories\ReservaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reserva de una mesa (RF15). "fecha" y "hora" están en la zona horaria del
 * restaurante; inicio() y fin() las convierten en un instante exacto.
 *
 * @property int $id_reserva
 * @property Carbon $fecha
 * @property string $hora
 * @property int $cantidad_personas
 * @property EstadoReserva $estado
 * @property string $cliente_nombre
 * @property string $cliente_telefono
 * @property string|null $observaciones
 * @property int $id_usuario
 * @property int $id_mesa
 * @property-read Mesa $mesa
 * @property-read Usuario $usuario
 */
class Reserva extends Model
{
    /** @use HasFactory<ReservaFactory> */
    use HasFactory;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_modificacion';

    protected $table = 'reservas';

    protected $primaryKey = 'id_reserva';

    protected $fillable = [
        'fecha',
        'hora',
        'cantidad_personas',
        'cliente_nombre',
        'cliente_telefono',
        'observaciones',
        'id_usuario',
        'id_mesa',
    ];

    protected $attributes = ['estado' => 'pendiente'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'cantidad_personas' => 'integer',
            'estado' => EstadoReserva::class,
            'fecha_creacion' => 'datetime',
            'fecha_modificacion' => 'datetime',
        ];
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'id_mesa', 'id_mesa');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    /** Momento exacto en que empieza la reserva. */
    public function inicio(): Carbon
    {
        return Carbon::parse($this->fecha->format('Y-m-d').' '.substr($this->hora, 0, 5), config('siar.zona_horaria'));
    }

    public function fin(): Carbon
    {
        return $this->inicio()->addMinutes(config('siar.reservas.duracion_minutos'));
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoReserva::PENDIENTE;
    }

    public function cancelar(): void
    {
        $this->forceFill(['estado' => EstadoReserva::CANCELADA])->save();
    }

    public function marcarCumplida(): void
    {
        $this->forceFill(['estado' => EstadoReserva::CUMPLIDA])->save();
    }

    public function marcarNoAsistio(): void
    {
        $this->forceFill(['estado' => EstadoReserva::NO_ASISTIO])->save();
    }
}
