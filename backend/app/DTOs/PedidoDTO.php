<?php

namespace App\DTOs;

/** Patrón DTO: datos para crear un pedido con sus productos (RF17, RF18). */
final readonly class PedidoDTO
{
    /** @param list<LineaPedidoDTO> $productos */
    public function __construct(
        public int $idMesa,
        public ?string $observaciones = null,
        public array $productos = [],
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $observaciones = isset($datos['observaciones']) ? trim((string) $datos['observaciones']) : null;

        return new self(
            idMesa: (int) $datos['id_mesa'],
            observaciones: $observaciones === '' ? null : $observaciones,
            productos: array_map(
                fn (array $linea) => LineaPedidoDTO::desdeArray($linea),
                array_values($datos['productos'] ?? []),
            ),
        );
    }
}
