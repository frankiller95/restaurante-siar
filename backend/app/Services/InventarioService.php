<?php

namespace App\Services;

use App\Contracts\Repositories\InsumoRepositoryInterface;
use App\DTOs\InsumoDTO;
use App\Enums\TipoEvento;
use App\Enums\TipoMovimiento;
use App\Events\EventoAuditable;
use App\Events\StockActualizado;
use App\Events\StockBajo;
use App\Exceptions\ReglaNegocioException;
use App\Models\DetallePedido;
use App\Models\DetalleReceta;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Módulo 5: RF23 a RF27 y RF39.
 *
 * Todo cambio de stock pasa por mover(): registra el movimiento en el kárdex,
 * publica StockBajo si el insumo llegó a su mínimo y, al final, StockActualizado
 * para que el menú se ponga al día (RF40).
 */
class InventarioService
{
    public const ELIMINACION_LOGICA = 'logica';

    public const ELIMINACION_FISICA = 'fisica';

    public function __construct(private readonly InsumoRepositoryInterface $insumos) {}

    // --- RF23, RF24, RF27: insumos y existencias -------------------------------

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return $this->insumos->buscar($filtros, $porPagina);
    }

    /** @return Collection<int, Insumo> */
    public function insumosBajoMinimo(): Collection
    {
        return $this->insumos->bajoMinimo();
    }

    /** RF23. El stock inicial queda como un ajuste en el kárdex. */
    public function registrarInsumo(InsumoDTO $dto): Insumo
    {
        $this->verificarNombre($dto->nombre);

        return DB::transaction(function () use ($dto) {
            $insumo = $this->insumos->crear($dto->aArray());

            EventoAuditable::dispatch(
                TipoEvento::INSUMO_CREADO,
                "Se registró el insumo {$insumo->nombre} ({$insumo->unidad_medida->abreviatura()}).",
            );

            if ($dto->stockInicial > 0) {
                $this->mover($insumo, $dto->stockInicial, TipoMovimiento::AJUSTE, ['motivo' => 'Stock inicial']);
                StockActualizado::dispatch([$insumo->id_insumo]);
            }

            return $insumo->refresh();
        });
    }

    /** RF24. El stock no se edita aquí: cambia con entradas, mermas y ajustes. */
    public function actualizarInsumo(Insumo $insumo, InsumoDTO $dto): Insumo
    {
        $this->verificarActivo($insumo);
        $this->verificarNombre($dto->nombre, $insumo->id_insumo);

        if ($dto->unidadMedida !== $insumo->unidad_medida && $insumo->tieneHistorial()) {
            throw ValidationException::withMessages([
                'unidad_medida' => 'No se puede cambiar la unidad de un insumo con movimientos o recetas.',
            ]);
        }

        $anterior = $insumo->nombre;
        $insumo = $this->insumos->actualizar($insumo, $dto->aArray());

        EventoAuditable::dispatch(TipoEvento::INSUMO_ACTUALIZADO, "Se actualizó el insumo {$anterior} (ahora: {$insumo->nombre}).");

        return $insumo;
    }

    /**
     * RF24. Con movimientos o recetas se desactiva (baja lógica); sin historial
     * se borra. No se desactiva si lo usa la receta de un producto activo.
     */
    public function eliminarInsumo(Insumo $insumo): string
    {
        $this->verificarActivo($insumo);

        if ($this->insumos->usadoEnRecetasActivas($insumo)) {
            throw new ReglaNegocioException(
                "{$insumo->nombre} está en la receta de productos activos. Quítalo de esas recetas antes de desactivarlo.",
                409,
            );
        }

        if ($insumo->tieneHistorial()) {
            $insumo->darDeBaja();
            $tipo = self::ELIMINACION_LOGICA;
        } else {
            $this->insumos->eliminar($insumo);
            $tipo = self::ELIMINACION_FISICA;
        }

        EventoAuditable::dispatch(TipoEvento::INSUMO_ELIMINADO, "Se dio de baja el insumo {$insumo->nombre}.");

        return $tipo;
    }

    /** RF27. Kárdex. @param array<string, mixed> $filtros */
    public function consultarKardex(Insumo $insumo, array $filtros, int $porPagina = 30): LengthAwarePaginator
    {
        return $this->insumos->movimientos($insumo, $filtros, $porPagina);
    }

    // --- RF25, RF26: movimientos manuales --------------------------------------

    /** RF25. Entrada por compra o reposición; actualiza el costo unitario. */
    public function registrarEntrada(
        Insumo $insumo,
        float $cantidad,
        ?float $costoUnitario = null,
        ?string $proveedor = null,
        ?Carbon $fecha = null,
    ): MovimientoInventario {
        $this->verificarActivo($insumo);

        $movimiento = DB::transaction(function () use ($insumo, $cantidad, $costoUnitario, $proveedor, $fecha) {
            if ($costoUnitario !== null) {
                $insumo->forceFill(['costo_unitario' => $costoUnitario])->save();
            }

            $movimiento = $this->mover($insumo, $cantidad, TipoMovimiento::ENTRADA, [
                'costo_unitario' => $costoUnitario ?? $insumo->costo_unitario,
                'proveedor' => $proveedor,
                'fecha' => $fecha ?? now(),
            ]);

            StockActualizado::dispatch([$insumo->id_insumo]);

            return $movimiento;
        });

        $proveedorTexto = $proveedor ? " de {$proveedor}" : '';
        EventoAuditable::dispatch(
            TipoEvento::INVENTARIO_ENTRADA,
            "Entrada de {$this->cantidadTexto($insumo, $cantidad)} de {$insumo->nombre}{$proveedorTexto}.",
        );

        return $movimiento;
    }

    /** RF26. Merma (dañado, vencido...). No puede superar el stock. */
    public function registrarMerma(Insumo $insumo, float $cantidad, string $motivo): MovimientoInventario
    {
        $this->verificarActivo($insumo);

        if (! $insumo->alcanzaPara($cantidad)) {
            throw ValidationException::withMessages([
                'cantidad' => "Solo hay {$this->cantidadTexto($insumo, (float) $insumo->stock_actual)} de {$insumo->nombre}.",
            ]);
        }

        $movimiento = DB::transaction(function () use ($insumo, $cantidad, $motivo) {
            $movimiento = $this->mover($insumo, -$cantidad, TipoMovimiento::MERMA, ['motivo' => $motivo]);
            StockActualizado::dispatch([$insumo->id_insumo]);

            return $movimiento;
        });

        EventoAuditable::dispatch(
            TipoEvento::INVENTARIO_MERMA,
            "Merma de {$this->cantidadTexto($insumo, $cantidad)} de {$insumo->nombre}. Motivo: {$motivo}.",
        );

        return $movimiento;
    }

    /** RF26. Ajuste por conteo físico: se registra la diferencia con lo contado. */
    public function ajustarConteo(Insumo $insumo, float $stockContado, string $motivo): MovimientoInventario
    {
        $this->verificarActivo($insumo);

        $diferencia = round($stockContado - (float) $insumo->stock_actual, 3);
        if ($diferencia == 0) {
            throw ValidationException::withMessages(['stock_contado' => 'El conteo coincide con el stock actual; no hay nada que ajustar.']);
        }

        $movimiento = DB::transaction(function () use ($insumo, $diferencia, $motivo) {
            $movimiento = $this->mover($insumo, $diferencia, TipoMovimiento::AJUSTE, ['motivo' => $motivo]);
            StockActualizado::dispatch([$insumo->id_insumo]);

            return $movimiento;
        });

        $signo = $diferencia > 0 ? '+' : '';
        EventoAuditable::dispatch(
            TipoEvento::INVENTARIO_AJUSTE,
            "Ajuste de {$insumo->nombre}: {$signo}{$this->cantidadTexto($insumo, $diferencia)}. Motivo: {$motivo}.",
        );

        return $movimiento;
    }

    // --- RF39: integración con pedidos ----------------------------------------

    /** Descuenta los insumos de las recetas de un pedido enviado a cocina. */
    public function descontarPorPedido(Pedido $pedido): void
    {
        $pedido->loadMissing('detalles.producto.receta.insumo');

        $consumo = [];
        foreach ($pedido->detalles as $detalle) {
            /** @var DetallePedido $detalle */
            foreach ($detalle->producto->receta as $linea) {
                /** @var DetalleReceta $linea */
                $consumo[$linea->id_insumo] = ($consumo[$linea->id_insumo] ?? 0) + $linea->cantidadPara($detalle->cantidad);
            }
        }

        if ($consumo === []) {
            return;
        }

        foreach ($consumo as $idInsumo => $cantidad) {
            $insumo = $this->insumos->obtenerPorId($idInsumo);
            $this->mover($insumo, -$cantidad, TipoMovimiento::CONSUMO_PEDIDO, [
                'id_pedido' => $pedido->id_pedido,
                'motivo' => "Pedido #{$pedido->id_pedido}",
            ]);
        }

        StockActualizado::dispatch(array_keys($consumo));
    }

    /**
     * Pedido cancelado después de enviarlo a cocina: se reintegra lo consumido.
     * Si ya estaba en preparación, se registra además como merma (el stock
     * no cambia, pero queda claro que se perdió).
     */
    public function reintegrarPorPedido(Pedido $pedido, bool $comoMerma): void
    {
        $consumido = $this->insumos->consumoNetoDePedido($pedido->id_pedido);

        if ($consumido === []) {
            return;
        }

        foreach ($consumido as $idInsumo => $cantidad) {
            $insumo = $this->insumos->obtenerPorId($idInsumo);

            $this->mover($insumo, $cantidad, TipoMovimiento::REINTEGRO_PEDIDO, [
                'id_pedido' => $pedido->id_pedido,
                'motivo' => "Pedido #{$pedido->id_pedido} cancelado",
            ]);

            if ($comoMerma) {
                $this->mover($insumo, -$cantidad, TipoMovimiento::MERMA, [
                    'id_pedido' => $pedido->id_pedido,
                    'motivo' => "Pedido #{$pedido->id_pedido} cancelado en preparación",
                ]);
            }
        }

        StockActualizado::dispatch(array_keys($consumido));
    }

    // --- Apoyo -----------------------------------------------------------------

    /**
     * Único punto que cambia el stock: actualiza el insumo, registra el
     * movimiento y avisa si el insumo cruzó su stock mínimo.
     *
     * @param  array<string, mixed>  $extra
     */
    private function mover(Insumo $insumo, float $cantidad, TipoMovimiento $tipo, array $extra = []): MovimientoInventario
    {
        $estabaBajo = $insumo->estaBajoMinimo();
        $resultante = $insumo->moverStock($cantidad);

        $movimiento = $this->insumos->registrarMovimiento([
            'id_insumo' => $insumo->id_insumo,
            'tipo' => $tipo,
            'cantidad' => round($cantidad, 3),
            'stock_resultante' => $resultante,
            'fecha' => now(),
            'id_usuario' => auth()->id(),
            ...$extra,
        ]);

        if (! $estabaBajo && $insumo->estaBajoMinimo()) {
            StockBajo::dispatch($insumo);
        }

        return $movimiento;
    }

    private function verificarNombre(string $nombre, ?int $exceptoId = null): void
    {
        if ($this->insumos->existeNombre($nombre, $exceptoId)) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un insumo con este nombre.']);
        }
    }

    private function verificarActivo(Insumo $insumo): void
    {
        if (! $insumo->estaActivo()) {
            throw new ReglaNegocioException('El insumo está dado de baja.');
        }
    }

    private function cantidadTexto(Insumo $insumo, float $cantidad): string
    {
        $numero = rtrim(rtrim(number_format(abs($cantidad), 3, ',', '.'), '0'), ',');

        return "{$numero} {$insumo->unidad_medida->abreviatura()}";
    }
}
