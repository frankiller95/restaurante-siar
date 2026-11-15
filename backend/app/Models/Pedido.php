<?php

namespace App\Models;

use App\Enums\EstadoFactura;
use App\Enums\EstadoPedido;
use App\Exceptions\TransicionPedidoException;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de una mesa (RF17 a RF22).
 *
 * @property int $id_pedido
 * @property int $id_mesa
 * @property int $id_usuario
 * @property EstadoPedido $estado
 * @property string|null $observaciones
 * @property string|null $motivo_cancelacion
 * @property Carbon|null $fecha_envio_cocina
 * @property Carbon|null $fecha_inicio_preparacion
 * @property Carbon|null $fecha_listo
 * @property Carbon|null $fecha_entrega
 * @property Carbon|null $fecha_cancelacion
 * @property Carbon|null $fecha_creacion
 * @property-read Mesa $mesa
 * @property-read Usuario $usuario
 * @property-read Collection<int, DetallePedido> $detalles
 */
class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_modificacion';

    protected $table = 'pedidos';

    protected $primaryKey = 'id_pedido';

    protected $fillable = [
        'id_mesa', 'id_usuario', 'observaciones', 'codigo_seguimiento', 'tipo_pedido',
        'cliente_nombre', 'cliente_telefono', 'tiempo_estimado_minutos', 'estimado_listo_en',
        'subtotal', 'descuento', 'propina', 'estado_pago',
    ];

    protected $attributes = ['estado' => 'pendiente'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPedido::class,
            'fecha_envio_cocina' => 'datetime',
            'fecha_inicio_preparacion' => 'datetime',
            'fecha_listo' => 'datetime',
            'fecha_entrega' => 'datetime',
            'fecha_cancelacion' => 'datetime',
            'fecha_creacion' => 'datetime',
            'fecha_modificacion' => 'datetime',
            'estimado_listo_en' => 'datetime',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'propina' => 'decimal:2',
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

    public function mesero(): BelongsTo
    {
        return $this->usuario();
    }

    public function detalles(): HasMany
    {
        $primaryKey = Schema::hasColumn('detalle_pedidos', 'id_detalle') ? 'id_detalle' : 'id_detalle_pedido';

        return $this->hasMany(DetallePedido::class, 'id_pedido', 'id_pedido')->orderBy($primaryKey);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajePedido::class, 'id_pedido', 'id_pedido');
    }

    /** Facturas del pedido (puede haber anuladas y una vigente). */
    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'id_pedido', 'id_pedido');
    }

    /** La factura vigente (pendiente o pagada) del pedido, si ya se facturó. */
    public function facturaVigente(): HasOne
    {
        return $this->hasOne(Factura::class, 'id_pedido', 'id_pedido')
            ->whereIn('estado', EstadoFactura::valoresVigentes())
            ->latest('id_factura');
    }

    public function total(): float
    {
        $productos = $this->detalles->sum(fn (DetallePedido $detalle) => $detalle->subtotal() - (float) ($detalle->descuento ?? 0));

        return round($productos + (float) ($this->propina ?? 0), 2);
    }

    /**
     * RF19: solo se modifica mientras está pendiente y antes de enviarlo a cocina
     * (después de enviarlo, el inventario ya descontó los insumos, RF39).
     */
    public function esEditable(): bool
    {
        return $this->estado === EstadoPedido::PENDIENTE && ! $this->fueEnviadoACocina();
    }

    public function fueEnviadoACocina(): bool
    {
        return $this->fecha_envio_cocina !== null;
    }

    // --- Transiciones ---------------------------------------------------------

    /** RF21. Sigue "Pendiente", pero ya en la cola de cocina. */
    public function enviarACocina(): void
    {
        if (! $this->esEditable() || $this->fueEnviadoACocina()) {
            throw new TransicionPedidoException($this->estado, 'enviar a cocina');
        }

        $this->forceFill(['fecha_envio_cocina' => now()])->save();
    }

    public function iniciarPreparacion(): void
    {
        if (! $this->fueEnviadoACocina()) {
            throw new TransicionPedidoException($this->estado, 'preparar (aún no se envía a cocina)');
        }

        $this->pasarA(EstadoPedido::EN_PREPARACION, 'preparar', ['fecha_inicio_preparacion' => now()]);
    }

    public function marcarListo(): void
    {
        $this->pasarA(EstadoPedido::LISTO, 'marcar como listo', ['fecha_listo' => now()]);
    }

    public function entregar(): void
    {
        $this->pasarA(EstadoPedido::ENTREGADO, 'entregar', ['fecha_entrega' => now()]);
    }

    /** RF31: lo usa el Módulo 6 (Facturación) cuando la factura queda pagada. */
    public function marcarFacturado(): void
    {
        $this->pasarA(EstadoPedido::FACTURADO, 'facturar');
    }

    public function cancelar(?string $motivo): void
    {
        $this->pasarA(EstadoPedido::CANCELADO, 'cancelar', [
            'motivo_cancelacion' => $motivo,
            'fecha_cancelacion' => now(),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function pasarA(EstadoPedido $destino, string $accion, array $extra = []): void
    {
        if (! $this->estado->puedePasarA($destino)) {
            throw new TransicionPedidoException($this->estado, $accion);
        }

        $this->forceFill(['estado' => $destino, ...$extra])->save();
    }
}
