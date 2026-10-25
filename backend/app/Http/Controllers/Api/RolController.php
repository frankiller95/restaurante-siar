<?php

namespace App\Http\Controllers\Api;

use App\DTOs\RolDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rol\AsignarPermisosRequest;
use App\Http\Requests\Rol\GuardarRolRequest;
use App\Http\Resources\RolResource;
use App\Models\Rol;
use App\Services\RolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CU-04 Gestionar roles.
 */
class RolController extends Controller
{
    public function __construct(private readonly RolService $roles) {}

    public function index(): AnonymousResourceCollection
    {
        return RolResource::collection($this->roles->listar());
    }

    public function show(Rol $rol): RolResource
    {
        return new RolResource($rol->load('permisos')->loadCount('usuarios'));
    }

    public function store(GuardarRolRequest $request): JsonResponse
    {
        $rol = $this->roles->crear(RolDTO::desdeArray($request->validated()));

        return response()->json([
            'message' => 'Rol creado.',
            'data' => new RolResource($rol),
        ], 201);
    }

    public function update(GuardarRolRequest $request, Rol $rol): JsonResponse
    {
        $rol = $this->roles->actualizar($rol, RolDTO::desdeArray($request->validated()));

        return response()->json([
            'message' => 'Rol actualizado.',
            'data' => new RolResource($rol),
        ]);
    }

    public function destroy(Rol $rol): JsonResponse
    {
        $this->roles->eliminar($rol);

        return response()->json(['message' => 'Rol eliminado.']);
    }

    public function asignarPermisos(AsignarPermisosRequest $request, Rol $rol): JsonResponse
    {
        $resultado = $this->roles->asignarPermisos(
            $rol,
            array_map('intval', $request->validated('permisos')),
            $request->user()->currentAccessToken()?->getKey(),
        );

        return response()->json([
            'message' => $resultado['sesiones_revocadas'] > 0
                ? "Permisos actualizados. Se cerraron {$resultado['sesiones_revocadas']} sesiones de usuarios con este rol."
                : 'Permisos actualizados.',
            'sesiones_revocadas' => $resultado['sesiones_revocadas'],
            'data' => new RolResource($resultado['rol']),
        ]);
    }
}
