<?php

namespace App\Models;

use App\Enums\EstadoFactura;
use App\Enums\TipoDescuento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Factura de un pedido entregado (RF28 a RF32).
 *
 * @property int $id_factura
 * @property string|null $numero
 * @property int $id_pedido
 * @property int|null $id_usuario
 * @property EstadoFactura $estado
 * @property string $cliente_nombre
 * @property string|null $cliente_documento
 * @property string $subtotal
 * @property string $descuento_promociones
 * @property TipoDescuento $descuento_tipo
 * @property string $descuento_valor
 * @property string $descuento
 * @property string|null $motivo_descuento
 * @property string $impuesto_nombre
 * @property string $impuesto_porcentaje
 * @property bool $impuesto_incluido
 * @property string $base_gravable
 * @property string $impuesto
 * @property string $propina
 * @property string $total
 * @property Carbon|null $fecha_emision
 * @property Carbon|null $fecha_pago
 * @property Carbon|null $comprobante_emitido_en
 * @property-read Pedido $pedido
 * @property-read Usuario|null $usuario
 * @property-read Collection<int, DetalleFactura> $detalles
 * @property-read Collection<int, Pago> $pagos
 */
class Factura extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_modificacion';

    protected $table = 'facturas';

    protected $primaryKey = 'id_factura';

    protected $fillable = [
        'id_pedido', 'id_usuario', 'cliente_nombre', 'cliente_documento', 'fecha_emision',
        'motivo_descuento',
        'subtotal', 'descuento_promociones', 'descuento_tipo', 'descuento_valor', 'descuento',
        'impuesto_nombre', 'impuesto_porcentaje', 'impuesto_incluido', 'base_gravable', 'impuesto',
        'propina', 'total',
    ];

    protected $attributes = ['estado' => 'pendiente'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoFactura::class,
            'descuento_tipo' => TipoDescuento::class,
            'impuesto_incluido' => 'boolean',
            'subtotal' => 'decimal:2',
            'descuento_promociones' => 'decimal:2',
            'descuento_valor' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto_porcentaje' => 'decimal:2',
            'base_gravable' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'propina' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha_emision' => 'datetime',
            'fecha_pago' => 'datetime',
            'fecha_anulacion' => 'datetime',
            'comprobante_emitido_en' => 'datetime',
            'fecha_creacion' => 'datetime',
            'fecha_modificacion' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido', 'id_pedido');
    }

    /** Cajero que generó la factura. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleFactura::class, 'id_factura', 'id_factura')->orderBy('id_detalle_factura');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_factura', 'id_factura')->orderBy('fecha')->orderBy('id_pago');
    }

    /** Total abonado hasta ahora. */
    public function totalPagado(): float
    {
        $pagos = $this->relationLoaded('pagos') ? $this->pagos : $this->pagos()->get();

        return round($pagos->sum(fn (Pago $pago) => (float) $pago->monto), 2);
    }

    /** Lo que falta por pagar (nunca negativo). */
    public function saldo(): float
    {
        return max(0, round((float) $this->total - $this->totalPagado(), 2));
    }

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoFactura::PENDIENTE;
    }

    public function tienePagos(): bool
    {
        return $this->totalPagado() > 0;
    }

    /** Solo las facturas vigentes de un pedido (no anuladas). */
    public function scopeVigentes($consulta)
    {
        return $consulta->whereIn('estado', EstadoFactura::valoresVigentes());
    }
}
