<?php

namespace App\Services;

use App\Contracts\Repositories\MesaRepositoryInterface;
use App\Contracts\Repositories\PedidoRepositoryInterface;
use App\Contracts\Repositories\ProductoRepositoryInterface;
use App\DTOs\LineaPedidoDTO;
use App\DTOs\PedidoDTO;
use App\Enums\EstadoMesa;
use App\Enums\EstadoPedido;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Events\PedidoCancelado;
use App\Events\PedidoEnviadoACocina;
use App\Exceptions\ReglaNegocioException;
use App\Exceptions\TransicionPedidoException;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF17 a RF22 (Módulo 4: Pedidos).
 */
class PedidoService
{
    public function __construct(
        private readonly PedidoRepositoryInterface $pedidos,
        private readonly MesaRepositoryInterface $mesas,
        private readonly ProductoRepositoryInterface $productos,
    ) {}

    /** RF22. @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return $this->pedidos->buscar($filtros, $porPagina);
    }

    /** RF22. Cola de cocina: enviados, en preparación y listos para entregar. */
    public function colaDeCocina(): Collection
    {
        return $this->pedidos->colaDeCocina();
    }

    /**
     * RF17 (+RF18). Si la mesa está libre, se ocupa (patrón State del Módulo 3).
     */
    public function crear(PedidoDTO $dto, Usuario $mesero): Pedido
    {
        $mesa = $this->mesaParaPedido($dto->idMesa);

        $pedido = DB::transaction(function () use ($dto, $mesero, $mesa) {
            if ($mesa->estado === EstadoMesa::LIBRE) {
                $mesa->ocupar();
            }

            $pedido = $this->pedidos->crear([
                'id_mesa' => $mesa->id_mesa,
                'id_usuario' => $mesero->id_usuario,
                'observaciones' => $dto->observaciones,
            ]);

            foreach ($dto->productos as $linea) {
                $this->sumarLinea($pedido, $linea);
            }

            return $pedido;
        });

        EventoAuditable::dispatch(
            TipoEvento::PEDIDO_CREADO,
            "Se creó el pedido #{$pedido->id_pedido} para la mesa {$mesa->numero}.",
        );

        return $this->pedidos->cargarDetalle($pedido);
    }

    /** RF18. */
    public function agregarProducto(Pedido $pedido, LineaPedidoDTO $linea): Pedido
    {
        $this->verificarEditable($pedido);

        $detalle = DB::transaction(fn () => $this->sumarLinea($pedido, $linea));

        EventoAuditable::dispatch(
            TipoEvento::PEDIDO_ACTUALIZADO,
            "Pedido #{$pedido->id_pedido}: se agregó {$linea->cantidad} x {$detalle->producto->nombre}.",
        );

        return $this->pedidos->cargarDetalle($pedido);
    }

    /** RF19. Cambiar cantidad u observación de una línea. */
    public function modificarProducto(Pedido $pedido, DetallePedido $detalle, int $cantidad, ?string $observacion): Pedido
    {
        $this->verificarEditable($pedido);

        $detalle->fill(['cantidad' => $cantidad, 'observacion' => $observacion])->save();

        EventoAuditable::dispatch(
            TipoEvento::PEDIDO_ACTUALIZADO,
            "Pedido #{$pedido->id_pedido}: {$detalle->producto->nombre} quedó en {$cantidad}.",
        );

        return $this->pedidos->cargarDetalle($pedido);
    }

    /** RF19. Quitar una línea. */
    public function quitarProducto(Pedido $pedido, DetallePedido $detalle): Pedido
    {
        $this->verificarEditable($pedido);

        $nombre = $detalle->producto->nombre;
        $detalle->delete();

        EventoAuditable::dispatch(TipoEvento::PEDIDO_ACTUALIZADO, "Pedido #{$pedido->id_pedido}: se quitó {$nombre}.");

        return $this->pedidos->cargarDetalle($pedido);
    }

    /** RF19. Observaciones generales del pedido. */
    public function actualizar(Pedido $pedido, ?string $observaciones): Pedido
    {
        $this->verificarEditable($pedido);

        $pedido->fill(['observaciones' => $observaciones])->save();

        return $this->pedidos->cargarDetalle($pedido);
    }

    /**
     * RF21. Necesita al menos un producto y que todos sigan disponibles.
     * Publica PedidoEnviadoACocina (el inventario descontará insumos, RF39).
     */
    public function enviarACocina(Pedido $pedido): Pedido
    {
        $pedido->load('detalles.producto.categoria');

        if ($pedido->detalles->isEmpty()) {
            throw new ReglaNegocioException('Agrega al menos un producto antes de enviar el pedido a cocina.');
        }

        $agotados = $pedido->detalles
            ->reject(fn (DetallePedido $detalle) => $detalle->producto->estaEnMenu())
            ->map(fn (DetallePedido $detalle) => $detalle->producto->nombre)
            ->unique()
            ->values();

        if ($agotados->isNotEmpty()) {
            throw new ReglaNegocioException(
                'Estos productos ya no están disponibles: '.$agotados->implode(', ').'. Quítalos del pedido para poder enviarlo.',
                409,
                'productos_no_disponibles',
            );
        }

        // En una transacción: si el inventario (Observer) falla, el pedido no queda enviado.
        DB::transaction(function () use ($pedido) {
            $pedido->enviarACocina();
            PedidoEnviadoACocina::dispatch($pedido);
        });

        EventoAuditable::dispatch(
            TipoEvento::PEDIDO_ENVIADO_COCINA,
            "El pedido #{$pedido->id_pedido} (mesa {$pedido->mesa->numero}) se envió a cocina.",
        );

        return $this->pedidos->cargarDetalle($pedido);
    }

    public function iniciarPreparacion(Pedido $pedido): Pedido
    {
        $pedido->iniciarPreparacion();

        return $this->registrarPaso($pedido, TipoEvento::PEDIDO_EN_PREPARACION, 'está en preparación');
    }

    public function marcarListo(Pedido $pedido): Pedido
    {
        $pedido->marcarListo();

        return $this->registrarPaso($pedido, TipoEvento::PEDIDO_LISTO, 'está listo para entregar');
    }

    public function entregar(Pedido $pedido): Pedido
    {
        $pedido->entregar();

        return $this->registrarPaso($pedido, TipoEvento::PEDIDO_ENTREGADO, 'se entregó en la mesa');
    }

    /**
     * RF20. En preparación el motivo es obligatorio. Publica PedidoCancelado
     * para que el inventario reintegre insumos o registre merma (RF39).
     */
    public function cancelar(Pedido $pedido, ?string $motivo): Pedido
    {
        $estabaEnPreparacion = $pedido->estado === EstadoPedido::EN_PREPARACION;
        $fueEnviado = $pedido->fueEnviadoACocina();

        if ($estabaEnPreparacion && blank($motivo)) {
            throw ValidationException::withMessages([
                'motivo' => 'Indica el motivo: el pedido ya se estaba preparando.',
            ]);
        }

        DB::transaction(function () use ($pedido, $motivo, $fueEnviado, $estabaEnPreparacion) {
            $pedido->cancelar($motivo);
            PedidoCancelado::dispatch($pedido, $fueEnviado, $estabaEnPreparacion);
        });

        $detalle = $motivo ? " Motivo: {$motivo}." : '';

        return $this->registrarPaso($pedido, TipoEvento::PEDIDO_CANCELADO, 'se canceló.'.$detalle, conPunto: false);
    }

    // --- Apoyo ----------------------------------------------------------------

    /** La mesa debe estar activa y libre u ocupada. */
    private function mesaParaPedido(int $idMesa): Mesa
    {
        $mesa = $this->mesas->obtenerPorId($idMesa);

        if (! $mesa || ! $mesa->estaActivo()) {
            throw ValidationException::withMessages(['id_mesa' => 'La mesa no existe o está dada de baja.']);
        }

        return match ($mesa->estado) {
            EstadoMesa::LIBRE, EstadoMesa::OCUPADA => $mesa,
            EstadoMesa::RESERVADA => throw new ReglaNegocioException(
                "La mesa {$mesa->numero} está reservada. Registra la llegada del cliente desde Reservas para tomar el pedido.",
                409,
            ),
            EstadoMesa::MANTENIMIENTO => throw new ReglaNegocioException("La mesa {$mesa->numero} está en mantenimiento.", 409),
        };
    }

    /**
     * RF18. Copia el precio actual del producto. Si ya hay una línea igual
     * (mismo producto, observación y precio), suma la cantidad.
     */
    private function sumarLinea(Pedido $pedido, LineaPedidoDTO $linea): DetallePedido
    {
        $producto = $this->productoDisponible($linea->idProducto);
        $precio = number_format((float) $producto->precio, 2, '.', '');

        $existente = $this->pedidos->lineaIgual($pedido, $producto->id_producto, $linea->observacion, $precio);

        if ($existente) {
            $nueva = $existente->cantidad + $linea->cantidad;
            if ($nueva > 50) {
                throw ValidationException::withMessages(['cantidad' => "Máximo 50 unidades de {$producto->nombre} por pedido."]);
            }
            $existente->forceFill(['cantidad' => $nueva])->save();

            return $existente->setRelation('producto', $producto);
        }

        return $this->pedidos->agregarLinea($pedido, [
            'id_producto' => $producto->id_producto,
            'cantidad' => $linea->cantidad,
            'precio_unitario' => $precio,
            'observacion' => $linea->observacion,
        ])->setRelation('producto', $producto);
    }

    private function productoDisponible(int $idProducto): Producto
    {
        $producto = $this->productos->obtenerPorId($idProducto);

        if (! $producto || ! $producto->estaEnMenu()) {
            $nombre = $producto?->nombre ?? 'El producto';
            throw ValidationException::withMessages(['id_producto' => "{$nombre} no está disponible en este momento."]);
        }

        return $producto;
    }

    private function verificarEditable(Pedido $pedido): void
    {
        if ($pedido->esEditable()) {
            return;
        }

        if ($pedido->estado === EstadoPedido::PENDIENTE) {
            throw new ReglaNegocioException(
                'El pedido ya se envió a cocina y no se puede modificar. Cancélalo o toma un pedido nuevo para la mesa.',
                409,
                'pedido_no_editable',
            );
        }

        throw new TransicionPedidoException($pedido->estado, 'modificar');
    }

    private function registrarPaso(Pedido $pedido, TipoEvento $tipo, string $texto, bool $conPunto = true): Pedido
    {
        $pedido->loadMissing('mesa');
        $fin = $conPunto ? '.' : '';

        EventoAuditable::dispatch($tipo, "El pedido #{$pedido->id_pedido} (mesa {$pedido->mesa->numero}) {$texto}{$fin}");

        return $this->pedidos->cargarDetalle($pedido);
    }
}
