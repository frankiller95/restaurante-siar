<?php

namespace App\DTOs;

use Illuminate\Support\Carbon;

/**
 * Patrón DTO: datos de una reserva (RF15). Fecha y hora en la zona horaria del restaurante.
 */
final readonly class ReservaDTO
{
    public function __construct(
        public string $fecha,
        public string $hora,
        public int $cantidadPersonas,
        public int $idMesa,
        public string $clienteNombre,
        public string $clienteTelefono,
        public ?string $observaciones = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $observaciones = isset($datos['observaciones']) ? trim((string) $datos['observaciones']) : null;

        return new self(
            fecha: (string) $datos['fecha'],
            hora: substr((string) $datos['hora'], 0, 5),
            cantidadPersonas: (int) $datos['cantidad_personas'],
            idMesa: (int) $datos['id_mesa'],
            clienteNombre: trim((string) $datos['cliente_nombre']),
            clienteTelefono: preg_replace('/\s+/', '', (string) $datos['cliente_telefono']),
            observaciones: $observaciones === '' ? null : $observaciones,
        );
    }

    public function inicio(): Carbon
    {
        return Carbon::parse("{$this->fecha} {$this->hora}", config('siar.zona_horaria'));
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'fecha' => $this->fecha,
            'hora' => $this->hora,
            'cantidad_personas' => $this->cantidadPersonas,
            'id_mesa' => $this->idMesa,
            'cliente_nombre' => $this->clienteNombre,
            'cliente_telefono' => $this->clienteTelefono,
            'observaciones' => $this->observaciones,
        ];
    }
}
