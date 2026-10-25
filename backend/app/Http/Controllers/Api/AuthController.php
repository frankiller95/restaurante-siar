<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CambiarContrasenaRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RestablecerContrasenaRequest;
use App\Http\Requests\Auth\SolicitarRecuperacionRequest;
use App\Http\Resources\PerfilResource;
use App\Services\AuthService;
use App\Services\RecuperacionContrasenaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CU-05 Iniciar sesión y CU-06 Recuperar contraseña.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly RecuperacionContrasenaService $recuperacion,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $resultado = $this->auth->iniciarSesion($request->validated('correo'), $request->validated('contrasena'));

        return response()->json([
            'token' => $resultado['token'],
            'expira' => $resultado['expira']->toIso8601String(),
            'usuario' => new PerfilResource($resultado['usuario']),
        ]);
    }

    public function perfil(Request $request): PerfilResource
    {
        return new PerfilResource($request->user()->load('rol.permisos'));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->cerrarSesion($request->user());

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function cambiarContrasena(CambiarContrasenaRequest $request): JsonResponse
    {
        $this->auth->cambiarContrasena(
            $request->user(),
            $request->validated('contrasena_actual'),
            $request->validated('contrasena'),
        );

        return response()->json(['message' => 'Contraseña actualizada. Se cerraron tus otras sesiones.']);
    }

    public function solicitarRecuperacion(SolicitarRecuperacionRequest $request): JsonResponse
    {
        $this->recuperacion->solicitar($request->validated('correo'));

        return response()->json(['message' => RecuperacionContrasenaService::MENSAJE_SOLICITUD]);
    }

    public function restablecerContrasena(RestablecerContrasenaRequest $request): JsonResponse
    {
        $this->recuperacion->restablecer(
            $request->validated('correo'),
            $request->validated('token'),
            $request->validated('contrasena'),
        );

        return response()->json(['message' => 'Tu contraseña se restableció. Ya puedes iniciar sesión.']);
    }
}
