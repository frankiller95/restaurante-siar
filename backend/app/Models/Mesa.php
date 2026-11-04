<?php

namespace App\Models;

use App\Enums\EstadoMesa;
use App\Enums\EstadoReserva;
use App\Estados\Mesa\ComportamientoMesa;
use App\Exceptions\TransicionMesaException;
use App\Models\Concerns\TieneBajaLogica;
use Database\Factories\MesaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mesa del restaurante (RF12 a RF16). Las transiciones de estado se delegan a
 * su objeto de comportamiento (patrón State, App\Estados\Mesa).
 *
 * @property int $id_mesa
 * @property int $numero
 * @property int $capacidad
 * @property string|null $ubicacion
 * @property EstadoMesa $estado
 * @property bool $activo
 */
class Mesa extends Model
{
    /** @use HasFactory<MesaFactory> */
    use HasFactory, TieneBajaLogica;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_modificacion';

    protected $table = 'mesas';

    protected $primaryKey = 'id_mesa';

    protected $fillable = ['numero', 'capacidad', 'ubicacion'];

    protected $attributes = [
        'estado' => 'libre',
        'activo' => true,
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'capacidad' => 'integer',
            'estado' => EstadoMesa::class,
            'activo' => 'boolean',
            'fecha_creacion' => 'datetime',
            'fecha_modificacion' => 'datetime',
            'fecha_baja' => 'datetime',
        ];
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'id_mesa', 'id_mesa');
    }

    /** Próxima reserva pendiente (para mostrarla en el plano de mesas). */
    public function proximaReserva(): HasOne
    {
        return $this->hasOne(Reserva::class, 'id_mesa', 'id_mesa')
            ->where('estado', EstadoReserva::PENDIENTE->value)
            ->orderBy('fecha')
            ->orderBy('hora');
    }

    // --- Patrón State --------------------------------------------------------

    public function estadoActual(): ComportamientoMesa
    {
        return $this->estado->comportamiento();
    }

    public function ocupar(): void
    {
        $this->cambiarEstado($this->estadoActual()->ocupar($this));
    }

    public function reservar(): void
    {
        $this->cambiarEstado($this->estadoActual()->reservar($this));
    }

    public function liberar(): void
    {
        $this->cambiarEstado($this->estadoActual()->liberar($this));
    }

    public function enviarAMantenimiento(): void
    {
        $this->cambiarEstado($this->estadoActual()->enviarAMantenimiento($this));
    }

    public function habilitar(): void
    {
        $this->cambiarEstado($this->estadoActual()->habilitar($this));
    }

    /**
     * Acciones que admite el estado actual. Se le pregunta al propio objeto de
     * estado, así el frontend muestra solo los botones válidos.
     *
     * @return list<string>
     */
    public function accionesPermitidas(): array
    {
        $estado = $this->estadoActual();
        $acciones = [];

        foreach (['ocupar', 'reservar', 'liberar', 'enviarAMantenimiento', 'habilitar'] as $accion) {
            try {
                $estado->{$accion}($this);
                $acciones[] = $accion;
            } catch (TransicionMesaException) {
                // El estado no admite esta acción.
            }
        }

        return $acciones;
    }

    public function admitePersonas(int $cantidad): bool
    {
        return $cantidad <= $this->capacidad;
    }

    private function cambiarEstado(EstadoMesa $nuevo): void
    {
        $this->forceFill(['estado' => $nuevo])->save();
    }
}
