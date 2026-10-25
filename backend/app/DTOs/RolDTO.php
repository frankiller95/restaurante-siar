<?php

namespace App\DTOs;

final readonly class RolDTO
{
    public function __construct(
        public string $nombreRol,
        public ?string $descripcion = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        return new self(
            nombreRol: trim($datos['nombre_rol']),
            descripcion: isset($datos['descripcion']) ? trim($datos['descripcion']) : null,
        );
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return ['nombre_rol' => $this->nombreRol, 'descripcion' => $this->descripcion];
    }
}
