<?php

namespace App\Http\Controllers\Api;

use App\DTOs\MesaDTO;
use App\Enums\EstadoMesa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mesa\GuardarMesaRequest;
use App\Http\Resources\MesaResource;
use App\Models\Mesa;
use App\Services\MesaService;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * RF12 a RF14 y RF16 (Gestión de Mesas).
 */
class MesaController extends Controller
{
    public function __construct(
        private readonly MesaService $mesas,
        private readonly ReservaService $reservas,
    ) {}

    /** RF14 Consultar estado de mesas (con resumen por estado). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::enum(EstadoMesa::class)],
            'ubicacion' => ['nullable', 'string', 'max:60'],
            'incluir_baja' => ['nullable', 'boolean'],
        ]);
        $filtros['incluir_baja'] = $request->boolean('incluir_baja');

        $this->reservas->sincronizar();

        return MesaResource::collection($this->mesas->listar($filtros))
            ->additional(['resumen' => $this->mesas->resumen()]);
    }

    public function show(Mesa $mesa): MesaResource
    {
        $this->reservas->sincronizar();

        return new MesaResource($mesa->refresh()->load('proximaReserva'));
    }

    /** RF12 Crear mesa. */
    public function store(GuardarMesaRequest $request): JsonResponse
    {
        $mesa = $this->mesas->crear(MesaDTO::desdeArray($request->validated()));

        return response()->json([
            'message' => "Mesa {$mesa->numero} creada.",
            'data' => new MesaResource($mesa->load('proximaReserva')),
        ], 201);
    }

    /** RF13 Modificar mesa. */
    public function update(GuardarMesaRequest $request, Mesa $mesa): JsonResponse
    {
        $mesa = $this->mesas->actualizar($mesa, MesaDTO::desdeArray($request->validated()));

        return response()->json([
            'message' => "Mesa {$mesa->numero} actualizada.",
            'data' => new MesaResource($mesa->load('proximaReserva')),
        ]);
    }

    public function destroy(Mesa $mesa): JsonResponse
    {
        $this->mesas->eliminar($mesa);

        return response()->json(['message' => "Mesa {$mesa->numero} dada de baja."]);
    }

    public function ocupar(Mesa $mesa): JsonResponse
    {
        return $this->respuesta($this->mesas->ocupar($mesa), 'quedó ocupada');
    }

    /** RF16 Liberar mesa. */
    public function liberar(Mesa $mesa): JsonResponse
    {
        return $this->respuesta($this->mesas->liberar($mesa), 'quedó libre');
    }

    public function mantenimiento(Mesa $mesa): JsonResponse
    {
        return $this->respuesta($this->mesas->enviarAMantenimiento($mesa), 'quedó en mantenimiento');
    }

    public function habilitar(Mesa $mesa): JsonResponse
    {
        return $this->respuesta($this->mesas->habilitar($mesa), 'quedó habilitada');
    }

    /** RF15: mesas libres para una reserva en esa fecha y hora. */
    public function disponibles(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'personas' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $inicio = Carbon::parse("{$datos['fecha']} {$datos['hora']}", config('siar.zona_horaria'));

        return MesaResource::collection($this->mesas->disponibles($inicio, (int) $datos['personas']));
    }

    private function respuesta(Mesa $mesa, string $texto): JsonResponse
    {
        return response()->json([
            'message' => "La mesa {$mesa->numero} {$texto}.",
            'data' => new MesaResource($mesa),
        ]);
    }
}
