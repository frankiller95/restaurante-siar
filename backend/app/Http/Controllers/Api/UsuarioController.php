<?php

namespace App\Http\Controllers\Api;

use App\DTOs\UsuarioDTO;
use App\Enums\EstadoUsuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\GuardarUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * CU-01 Registrar, CU-02 Editar y CU-03 Eliminar usuario.
 */
class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'id_rol' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::enum(EstadoUsuario::class)],
            'por_pagina' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        return UsuarioResource::collection(
            $this->usuarios->listar($filtros, (int) ($filtros['por_pagina'] ?? 15))
        );
    }

    public function show(Usuario $usuario): UsuarioResource
    {
        return new UsuarioResource($usuario->load('rol'));
    }

    public function store(GuardarUsuarioRequest $request): JsonResponse
    {
        $resultado = $this->usuarios->registrar(UsuarioDTO::desdeArray($request->validated()));

        return response()->json([
            'message' => $resultado['correo_enviado']
                ? 'Usuario registrado. Las credenciales se enviaron a su correo.'
                : 'Usuario registrado, pero no se pudo enviar el correo con las credenciales. Puedes reenviarlas desde la lista de usuarios.',
            'correo_enviado' => $resultado['correo_enviado'],
            'data' => new UsuarioResource($resultado['usuario']),
        ], 201);
    }

    public function update(GuardarUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $resultado = $this->usuarios->actualizar(
            $usuario,
            UsuarioDTO::desdeArray($request->validated()),
            $request->user(),
        );

        return response()->json([
            'message' => $resultado['sesiones_revocadas']
                ? 'Usuario actualizado. Se cerraron sus sesiones activas por el cambio de rol o estado.'
                : 'Usuario actualizado.',
            'sesiones_revocadas' => $resultado['sesiones_revocadas'],
            'data' => new UsuarioResource($resultado['usuario']),
        ]);
    }

    public function destroy(Request $request, Usuario $usuario): JsonResponse
    {
        $tipo = $this->usuarios->eliminar($usuario, $request->user());

        return response()->json([
            'message' => $tipo === UsuarioService::ELIMINACION_LOGICA
                ? 'El usuario tiene historial asociado, así que se inactivó en lugar de borrarse.'
                : 'Usuario eliminado.',
            'tipo_eliminacion' => $tipo,
        ]);
    }

    public function reenviarCredenciales(Usuario $usuario): JsonResponse
    {
        $enviado = $this->usuarios->reenviarCredenciales($usuario);

        return response()->json([
            'message' => $enviado
                ? 'Se generó una nueva contraseña temporal y se envió al correo del usuario.'
                : 'No se pudo enviar el correo. Intenta de nuevo más tarde.',
            'correo_enviado' => $enviado,
        ], $enviado ? 200 : 503);
    }
}
