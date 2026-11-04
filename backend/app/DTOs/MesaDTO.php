<?php

namespace App\DTOs;

final readonly class MesaDTO
{
    public function __construct(
        public int $numero,
        public int $capacidad,
        public ?string $ubicacion = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $ubicacion = isset($datos['ubicacion']) ? trim((string) $datos['ubicacion']) : null;

        return new self(
            numero: (int) $datos['numero'],
            capacidad: (int) $datos['capacidad'],
            ubicacion: $ubicacion === '' ? null : $ubicacion,
        );
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return ['numero' => $this->numero, 'capacidad' => $this->capacidad, 'ubicacion' => $this->ubicacion];
    }
}
