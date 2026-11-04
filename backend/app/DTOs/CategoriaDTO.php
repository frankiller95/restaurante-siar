<?php

namespace App\DTOs;

final readonly class CategoriaDTO
{
    public function __construct(
        public string $nombre,
        public ?string $descripcion = null,
        public ?bool $activa = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $descripcion = isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : null;

        return new self(
            nombre: trim((string) $datos['nombre']),
            descripcion: $descripcion === '' ? null : $descripcion,
            activa: array_key_exists('activa', $datos) ? filter_var($datos['activa'], FILTER_VALIDATE_BOOLEAN) : null,
        );
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        $datos = ['nombre' => $this->nombre, 'descripcion' => $this->descripcion];

        if ($this->activa !== null) {
            $datos['activa'] = $this->activa;
        }

        return $datos;
    }
}
