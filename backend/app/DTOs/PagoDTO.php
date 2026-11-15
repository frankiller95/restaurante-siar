<?php

namespace App\DTOs;

use App\Enums\MetodoPago;

/** Patrón DTO: datos de un pago (RF31). */
final readonly class PagoDTO
{
    public function __construct(
        public MetodoPago $metodo,
        public float $monto,
        public ?float $montoRecibido = null,
        public ?string $referencia = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $referencia = trim((string) ($datos['referencia'] ?? ''));

        return new self(
            metodo: MetodoPago::from($datos['metodo']),
            monto: round((float) $datos['monto'], 2),
            montoRecibido: isset($datos['monto_recibido']) && $datos['monto_recibido'] !== ''
                ? round((float) $datos['monto_recibido'], 2)
                : null,
            referencia: $referencia === '' ? null : $referencia,
        );
    }
}
