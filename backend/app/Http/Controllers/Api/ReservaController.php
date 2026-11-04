<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ReservaDTO;
use App\Enums\EstadoReserva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mesa\GuardarReservaRequest;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * RF15 Reservar mesas.
 */
class ReservaController extends Controller
{
    public function __construct(private readonly ReservaService $reservas) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::enum(EstadoReserva::class)],
            'id_mesa' => ['nullable', 'integer'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        return ReservaResource::collection($this->reservas->listar($filtros, (int) ($filtros['por_pagina'] ?? 20)));
    }

    public function store(GuardarReservaRequest $request): JsonResponse
    {
        $reserva = $this->reservas->reservar(ReservaDTO::desdeArray($request->validated()), $request->user());

        return response()->json([
            'message' => "Reserva registrada para {$reserva->cliente_nombre} en la mesa {$reserva->mesa->numero}.",
            'data' => new ReservaResource($reserva),
        ], 201);
    }

    public function cancelar(Reserva $reserva): JsonResponse
    {
        $reserva = $this->reservas->cancelar($reserva);

        return response()->json([
            'message' => "Se canceló la reserva de {$reserva->cliente_nombre}.",
            'data' => new ReservaResource($reserva),
        ]);
    }

    public function llegada(Reserva $reserva): JsonResponse
    {
        $reserva = $this->reservas->registrarLlegada($reserva);

        return response()->json([
            'message' => "{$reserva->cliente_nombre} llegó: la mesa {$reserva->mesa->numero} quedó ocupada.",
            'data' => new ReservaResource($reserva),
        ]);
    }
}
