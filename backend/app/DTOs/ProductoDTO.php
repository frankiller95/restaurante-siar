<?php

namespace App\DTOs;

/**
 * Patrón DTO: datos de entrada para crear o modificar un producto (CU01, CU02).
 */
final readonly class ProductoDTO
{
    public function __construct(
        public string $nombre,
        public ?string $descripcion,
        public string $precio,
        public int $idCategoria,
        public bool $confirmarDuplicado = false,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $descripcion = isset($datos['descripcion']) ? trim((string) $datos['descripcion']) : null;

        return new self(
            nombre: trim((string) $datos['nombre']),
            descripcion: $descripcion === '' ? null : $descripcion,
            precio: number_format((float) $datos['precio'], 2, '.', ''),
            idCategoria: (int) $datos['id_categoria'],
            confirmarDuplicado: filter_var($datos['confirmar_duplicado'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /** @return array<string, mixed> columnas de la tabla productos */
    public function aArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'precio' => $this->precio,
            'id_categoria' => $this->idCategoria,
        ];
    }
}
