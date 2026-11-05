<?php

namespace App\Enums;

/**
 * Estados del pedido y sus transiciones permitidas (máquina de estados).
 *
 * pendiente → en_preparacion → listo → entregado → facturado
 * pendiente / en_preparacion → cancelado
 */
enum EstadoPedido: string
{
    case PENDIENTE = 'pendiente';
    case EN_PREPARACION = 'en_preparacion';
    case LISTO = 'listo';
    case ENTREGADO = 'entregado';
    case FACTURADO = 'facturado';
    case CANCELADO = 'cancelado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::EN_PREPARACION => 'En preparación',
            self::LISTO => 'Listo',
            self::ENTREGADO => 'Entregado',
            self::FACTURADO => 'Facturado',
            self::CANCELADO => 'Cancelado',
        };
    }

    /** @return list<self> */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::PENDIENTE => [self::EN_PREPARACION, self::CANCELADO],
            self::EN_PREPARACION => [self::LISTO, self::CANCELADO],
            self::LISTO => [self::ENTREGADO],
            self::ENTREGADO => [self::FACTURADO],
            self::FACTURADO, self::CANCELADO => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Abierto = todavía no se ha facturado ni cancelado. */
    public function esAbierto(): bool
    {
        return ! in_array($this, [self::FACTURADO, self::CANCELADO], true);
    }

    /** @return list<string> */
    public static function valoresAbiertos(): array
    {
        return array_values(array_map(
            fn (self $estado) => $estado->value,
            array_filter(self::cases(), fn (self $estado) => $estado->esAbierto()),
        ));
    }
}
