<?php

namespace App\Services;

use App\Contracts\Repositories\MesaRepositoryInterface;
use App\Contracts\Repositories\ReservaRepositoryInterface;
use App\DTOs\MesaDTO;
use App\Enums\EstadoMesa;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Mesa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * RF12 Crear, RF13 Modificar, RF14 Consultar estado y RF16 Liberar mesas.
 * Las transiciones las decide el estado de la mesa (patrón State).
 */
class MesaService
{
    public function __construct(
        private readonly MesaRepositoryInterface $mesas,
        private readonly ReservaRepositoryInterface $reservas,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros = []): Collection
    {
        return $this->mesas->buscar($filtros);
    }

    /** @return array<string, int> */
    public function resumen(): array
    {
        return $this->mesas->resumenPorEstado();
    }

    /** RF12. La mesa nace libre. */
    public function crear(MesaDTO $dto): Mesa
    {
        $this->verificarNumero($dto->numero);

        $mesa = $this->mesas->crear($dto->aArray());

        EventoAuditable::dispatch(TipoEvento::MESA_CREADA, "Se creó la mesa {$mesa->numero} para {$mesa->capacidad} personas.");

        return $mesa;
    }

    /** RF13. La capacidad no puede quedar por debajo de una reserva pendiente. */
    public function actualizar(Mesa $mesa, MesaDTO $dto): Mesa
    {
        $this->verificarActiva($mesa);
        $this->verificarNumero($dto->numero, $mesa->id_mesa);

        $maxReservado = $this->reservas->pendientesDeMesa($mesa->id_mesa)->max('cantidad_personas') ?? 0;
        if ($dto->capacidad < $maxReservado) {
            throw ValidationException::withMessages([
                'capacidad' => "La mesa tiene una reserva pendiente para {$maxReservado} personas.",
            ]);
        }

        $anterior = $mesa->numero;
        $mesa = $this->mesas->actualizar($mesa, $dto->aArray());

        EventoAuditable::dispatch(
            TipoEvento::MESA_ACTUALIZADA,
            "Se actualizó la mesa {$anterior} (ahora: mesa {$mesa->numero}, {$mesa->capacidad} personas).",
        );

        return $mesa;
    }

    /** Baja lógica: solo libre o en mantenimiento y sin reservas pendientes. */
    public function eliminar(Mesa $mesa): void
    {
        $this->verificarActiva($mesa);

        if (! in_array($mesa->estado, [EstadoMesa::LIBRE, EstadoMesa::MANTENIMIENTO], true)) {
            throw new ReglaNegocioException("La mesa {$mesa->numero} está {$this->texto($mesa)}; libérala antes de darla de baja.", 409);
        }
        if ($this->reservas->pendientesDeMesa($mesa->id_mesa)->isNotEmpty()) {
            throw new ReglaNegocioException("La mesa {$mesa->numero} tiene reservas pendientes; cancélalas antes de darla de baja.", 409);
        }

        $mesa->darDeBaja();

        EventoAuditable::dispatch(TipoEvento::MESA_ELIMINADA, "Se dio de baja la mesa {$mesa->numero}.");
    }

    /** Clientes sin reserva: la mesa pasa a ocupada. */
    public function ocupar(Mesa $mesa): Mesa
    {
        $this->verificarActiva($mesa);

        if ($mesa->estado === EstadoMesa::RESERVADA) {
            throw new ReglaNegocioException(
                "La mesa {$mesa->numero} está reservada. Si llegó el cliente de la reserva, registra su llegada desde Reservas.",
                409,
            );
        }

        return $this->transicion($mesa, fn () => $mesa->ocupar(), 'ocupada');
    }

    /** RF16. No se libera si tiene pedidos abiertos. */
    public function liberar(Mesa $mesa): Mesa
    {
        $this->verificarActiva($mesa);

        if ($mesa->estado === EstadoMesa::RESERVADA) {
            throw new ReglaNegocioException(
                "La mesa {$mesa->numero} está apartada por una reserva. Cancela la reserva para liberarla.",
                409,
            );
        }
        if ($this->mesas->tienePedidosAbiertos($mesa)) {
            throw new ReglaNegocioException("La mesa {$mesa->numero} tiene pedidos abiertos; factúralos antes de liberarla.", 409);
        }

        return $this->transicion($mesa, fn () => $mesa->liberar(), 'libre');
    }

    public function enviarAMantenimiento(Mesa $mesa): Mesa
    {
        $this->verificarActiva($mesa);

        return $this->transicion($mesa, fn () => $mesa->enviarAMantenimiento(), 'en mantenimiento');
    }

    public function habilitar(Mesa $mesa): Mesa
    {
        $this->verificarActiva($mesa);

        return $this->transicion($mesa, fn () => $mesa->habilitar(), 'habilitada (libre)');
    }

    /**
     * RF15: mesas que pueden recibir una reserva en ese horario.
     *
     * @return Collection<int, Mesa>
     */
    public function disponibles(Carbon $inicio, int $personas): Collection
    {
        return $this->mesas->candidatasParaReserva($personas)
            ->reject(fn (Mesa $mesa) => $this->reservas->hayCruce($mesa->id_mesa, $inicio))
            ->values();
    }

    private function transicion(Mesa $mesa, callable $accion, string $resultado): Mesa
    {
        $anterior = $mesa->estado->etiqueta();
        $accion();

        EventoAuditable::dispatch(
            TipoEvento::MESA_ESTADO_CAMBIADO,
            "La mesa {$mesa->numero} pasó de {$anterior} a {$resultado}.",
        );

        return $mesa->load('proximaReserva');
    }

    private function verificarNumero(int $numero, ?int $exceptoId = null): void
    {
        if ($this->mesas->existeNumero($numero, $exceptoId)) {
            throw ValidationException::withMessages(['numero' => "Ya existe una mesa con el número {$numero}."]);
        }
    }

    private function verificarActiva(Mesa $mesa): void
    {
        if (! $mesa->estaActivo()) {
            throw new ReglaNegocioException('La mesa está dada de baja.');
        }
    }

    private function texto(Mesa $mesa): string
    {
        return mb_strtolower($mesa->estado->etiqueta());
    }
}
