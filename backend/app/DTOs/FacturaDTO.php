<?php

namespace App\DTOs;

use App\Enums\TipoDescuento;

/** Patrón DTO: datos para generar una factura (RF28) y aplicar su descuento (RF30). */
final readonly class FacturaDTO
{
    public function __construct(
        public int $idPedido,
        public string $clienteNombre = 'Consumidor final',
        public ?string $clienteDocumento = null,
        public TipoDescuento $descuentoTipo = TipoDescuento::NINGUNO,
        public float $descuentoValor = 0,
        public ?string $motivoDescuento = null,
    ) {}

    /** @param array<string, mixed> $datos */
    public static function desdeArray(array $datos): self
    {
        $nombre = trim((string) ($datos['cliente_nombre'] ?? ''));
        $documento = trim((string) ($datos['cliente_documento'] ?? ''));
        $motivo = trim((string) ($datos['motivo_descuento'] ?? ''));

        return new self(
            idPedido: (int) $datos['id_pedido'],
            clienteNombre: $nombre === '' ? 'Consumidor final' : $nombre,
            clienteDocumento: $documento === '' ? null : $documento,
            descuentoTipo: TipoDescuento::tryFrom((string) ($datos['descuento_tipo'] ?? '')) ?? TipoDescuento::NINGUNO,
            descuentoValor: round((float) ($datos['descuento_valor'] ?? 0), 2),
            motivoDescuento: $motivo === '' ? null : $motivo,
        );
    }
}
