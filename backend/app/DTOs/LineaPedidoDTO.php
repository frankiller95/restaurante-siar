<?php

namespace App\DTOs;

/** Un producto dentro de un pedido (RF18). */
final readonly class LineaPedidoDTO
{
    public function __construct(
        public int $idProducto,
        public int $cantidad,
        public ?string $observacion = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $observacion = isset($datos['observacion']) ? trim((string) $datos['observacion']) : null;

        return new self(
            idProducto: (int) $datos['id_producto'],
            cantidad: (int) $datos['cantidad'],
            observacion: $observacion === '' ? null : $observacion,
        );
    }
}
