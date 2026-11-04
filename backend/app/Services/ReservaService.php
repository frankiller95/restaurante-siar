<?php

namespace App\Services;

use App\Contracts\Repositories\MesaRepositoryInterface;
use App\Contracts\Repositories\ReservaRepositoryInterface;
use App\DTOs\ReservaDTO;
use App\Enums\EstadoMesa;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF15 Reservar mesas.
 *
 * La mesa pasa a "Reservada" cuando falta la ventana configurada (1 h) para la
 * reserva, y si el cliente no llega dentro de la tolerancia (20 min) la reserva
 * queda como "No asistió" y la mesa se libera. sincronizar() aplica esas reglas;
 * se llama al consultar mesas o reservas, así no hace falta una tarea programada.
 */
class ReservaService
{
    public function __construct(
        private readonly ReservaRepositoryInterface $reservas,
        private readonly MesaRepositoryInterface $mesas,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        $this->sincronizar();

        return $this->reservas->buscar($filtros, $porPagina);
    }

    /** RF15. La registra el personal a nombre del cliente. */
    public function reservar(ReservaDTO $dto, Usuario $registradaPor): Reserva
    {
        $mesa = $this->mesas->obtenerPorId($dto->idMesa);
        $inicio = $dto->inicio();

        if (! $mesa || ! $mesa->estaActivo()) {
            throw ValidationException::withMessages(['id_mesa' => 'La mesa seleccionada no existe o está dada de baja.']);
        }
        if ($mesa->estado === EstadoMesa::MANTENIMIENTO) {
            throw ValidationException::withMessages(['id_mesa' => "La mesa {$mesa->numero} está en mantenimiento."]);
        }
        if (! $mesa->admitePersonas($dto->cantidadPersonas)) {
            throw ValidationException::withMessages([
                'cantidad_personas' => "La mesa {$mesa->numero} es para máximo {$mesa->capacidad} personas.",
            ]);
        }
        if ($inicio->lte(now())) {
            throw ValidationException::withMessages(['hora' => 'La fecha y hora de la reserva deben ser futuras.']);
        }
        if ($this->reservas->hayCruce($mesa->id_mesa, $inicio)) {
            $horas = intdiv(config('siar.reservas.duracion_minutos'), 60);
            throw new ReglaNegocioException(
                "La mesa {$mesa->numero} ya tiene una reserva a menos de {$horas} h de ese horario. Elige otra hora u otra mesa.",
                409,
                'cruce_reserva',
            );
        }

        $reserva = $this->reservas->crear([...$dto->aArray(), 'id_usuario' => $registradaPor->id_usuario]);

        EventoAuditable::dispatch(
            TipoEvento::RESERVA_CREADA,
            "Reserva de {$reserva->cliente_nombre} para {$reserva->cantidad_personas} personas en la mesa {$mesa->numero}, el {$dto->fecha} a las {$dto->hora}.",
        );

        // Si es para dentro de poco, la mesa queda reservada de una vez.
        $this->sincronizar();

        return $reserva->refresh()->load(['mesa', 'usuario:id_usuario,nombres']);
    }

    public function cancelar(Reserva $reserva): Reserva
    {
        $this->verificarPendiente($reserva);

        DB::transaction(function () use ($reserva) {
            $reserva->cancelar();
            $this->liberarSiCorresponde($reserva->mesa);
        });

        EventoAuditable::dispatch(
            TipoEvento::RESERVA_CANCELADA,
            "Se canceló la reserva de {$reserva->cliente_nombre} (mesa {$reserva->mesa->numero}).",
        );

        return $reserva->load(['mesa', 'usuario:id_usuario,nombres']);
    }

    /** Llegó el cliente: la reserva se cumple y la mesa queda ocupada. */
    public function registrarLlegada(Reserva $reserva): Reserva
    {
        $this->sincronizar();
        $reserva->refresh();
        $this->verificarPendiente($reserva);

        $mesa = $reserva->mesa;
        if (! in_array($mesa->estado, [EstadoMesa::LIBRE, EstadoMesa::RESERVADA], true)) {
            throw new ReglaNegocioException(
                "La mesa {$mesa->numero} está {$this->texto($mesa)}. Libérala o asigna otra mesa al cliente.",
                409,
            );
        }

        DB::transaction(function () use ($reserva, $mesa) {
            $reserva->marcarCumplida();
            $mesa->ocupar();
        });

        EventoAuditable::dispatch(
            TipoEvento::RESERVA_CUMPLIDA,
            "Llegó {$reserva->cliente_nombre}; la mesa {$mesa->numero} quedó ocupada.",
        );

        return $reserva->load(['mesa', 'usuario:id_usuario,nombres']);
    }

    /**
     * 1) Reservas vencidas (no llegó el cliente) → "No asistió" y se libera la mesa.
     * 2) Reservas dentro de la ventana → su mesa libre pasa a "Reservada".
     */
    public function sincronizar(): void
    {
        $ahora = now();

        foreach ($this->reservas->vencidas($ahora) as $reserva) {
            DB::transaction(function () use ($reserva) {
                $reserva->marcarNoAsistio();
                $this->liberarSiCorresponde($reserva->mesa);
            });

            EventoAuditable::dispatch(
                TipoEvento::RESERVA_NO_ASISTIO,
                "{$reserva->cliente_nombre} no llegó a su reserva (mesa {$reserva->mesa->numero}).",
            );
        }

        foreach ($this->reservas->enVentana($ahora) as $reserva) {
            $mesa = $reserva->mesa;

            if ($mesa->estaActivo() && $mesa->estado === EstadoMesa::LIBRE) {
                $mesa->reservar();

                EventoAuditable::dispatch(
                    TipoEvento::MESA_ESTADO_CAMBIADO,
                    "La mesa {$mesa->numero} quedó reservada para {$reserva->cliente_nombre} ({$reserva->inicio()->format('H:i')}).",
                );
            }
        }
    }

    /** Libera la mesa reservada si ya no tiene otra reserva dentro de la ventana. */
    private function liberarSiCorresponde(Mesa $mesa): void
    {
        $mesa->refresh();
        if ($mesa->estado !== EstadoMesa::RESERVADA) {
            return;
        }

        $sigueApartada = $this->reservas->enVentana(now())->contains('id_mesa', $mesa->id_mesa);
        if (! $sigueApartada) {
            $mesa->liberar();
        }
    }

    private function verificarPendiente(Reserva $reserva): void
    {
        if (! $reserva->estaPendiente()) {
            throw new ReglaNegocioException("La reserva ya está {$this->textoReserva($reserva)}.");
        }
    }

    private function texto(Mesa $mesa): string
    {
        return mb_strtolower($mesa->estado->etiqueta());
    }

    private function textoReserva(Reserva $reserva): string
    {
        return mb_strtolower($reserva->estado->etiqueta());
    }
}
