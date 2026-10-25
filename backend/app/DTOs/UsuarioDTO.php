<?php

namespace App\DTOs;

use App\Enums\EstadoUsuario;

/**
 * Patrón DTO: datos de entrada para registrar o editar un usuario. El controlador
 * lo arma desde la petición ya validada y el servicio trabaja solo con él.
 */
final readonly class UsuarioDTO
{
    public function __construct(
        public string $nombres,
        public string $documento,
        public string $correo,
        public int $idRol,
        public ?EstadoUsuario $estado = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        return new self(
            nombres: trim($datos['nombres']),
            documento: trim($datos['documento']),
            correo: mb_strtolower(trim($datos['correo'])),
            idRol: (int) $datos['id_rol'],
            estado: isset($datos['estado']) ? EstadoUsuario::from($datos['estado']) : null,
        );
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return array_filter([
            'nombres' => $this->nombres,
            'documento' => $this->documento,
            'correo' => $this->correo,
            'id_rol' => $this->idRol,
            'estado' => $this->estado,
        ], fn ($valor) => $valor !== null);
    }
}
