<?php

namespace App\DTOs;

use App\Enums\UnidadMedida;

/** Patrón DTO: datos de un insumo (RF23, RF24). */
final readonly class InsumoDTO
{
    public function __construct(
        public string $nombre,
        public UnidadMedida $unidadMedida,
        public float $stockMinimo,
        public float $costoUnitario,
        public float $stockInicial = 0,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        return new self(
            nombre: trim((string) $datos['nombre']),
            unidadMedida: UnidadMedida::from($datos['unidad_medida']),
            stockMinimo: round((float) ($datos['stock_minimo'] ?? 0), 3),
            costoUnitario: round((float) ($datos['costo_unitario'] ?? 0), 2),
            stockInicial: round((float) ($datos['stock_inicial'] ?? 0), 3),
        );
    }

    /** @return array<string, mixed> columnas editables (el stock cambia solo con movimientos) */
    public function aArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'unidad_medida' => $this->unidadMedida,
            'stock_minimo' => $this->stockMinimo,
            'costo_unitario' => $this->costoUnitario,
        ];
    }
}
