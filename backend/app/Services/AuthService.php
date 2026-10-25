<?php

namespace App\Services;

use App\Contracts\Repositories\UsuarioRepositoryInterface;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * CU-05 Iniciar sesión, cierre de sesión y cambio de contraseña propia.
 */
class AuthService
{
    public const MENSAJE_CREDENCIALES_INVALIDAS = 'Correo o contraseña incorrectos.';

    public function __construct(private readonly UsuarioRepositoryInterface $usuarios) {}

    /**
     * @return array{usuario: Usuario, token: string, expira: Carbon}
     */
    public function iniciarSesion(string $correo, string $contrasena): array
    {
        $usuario = $this->usuarios->obtenerPorCorreo($correo);

        // 4a: mensaje genérico, sin decir si falló el correo o la contraseña.
        if (! $usuario || ! $usuario->autenticar($contrasena)) {
            EventoAuditable::dispatch(
                TipoEvento::INICIO_SESION_FALLIDO,
                "Intento fallido de inicio de sesión con el correo {$correo}.",
                $usuario?->id_usuario,
            );

            throw new ReglaNegocioException(self::MENSAJE_CREDENCIALES_INVALIDAS, 422);
        }

        // 4b: usuario inactivo.
        if (! $usuario->estaActivo()) {
            EventoAuditable::dispatch(
                TipoEvento::INICIO_SESION_FALLIDO,
                "Intento de inicio de sesión de un usuario inactivo ({$usuario->correo}).",
                $usuario->id_usuario,
            );

            throw new ReglaNegocioException('Tu usuario está inactivo. Comunícate con el administrador.', 403);
        }

        $expira = now()->addMinutes(config('siar.duracion_sesion_minutos'));
        $token = $usuario->createToken('siar-web', ['*'], $expira);

        EventoAuditable::dispatch(TipoEvento::INICIO_SESION, "Inicio de sesión de {$usuario->correo}.", $usuario->id_usuario);

        return ['usuario' => $usuario, 'token' => $token->plainTextToken, 'expira' => $expira];
    }

    public function cerrarSesion(Usuario $usuario): void
    {
        $usuario->currentAccessToken()?->delete();

        EventoAuditable::dispatch(TipoEvento::CIERRE_SESION, "Cierre de sesión de {$usuario->correo}.", $usuario->id_usuario);
    }

    /** Cambia la contraseña propia y cierra las demás sesiones del usuario. */
    public function cambiarContrasena(Usuario $usuario, string $contrasenaActual, string $contrasenaNueva): void
    {
        if (! $usuario->autenticar($contrasenaActual)) {
            throw ValidationException::withMessages([
                'contrasena_actual' => 'La contraseña actual no es correcta.',
            ]);
        }

        $this->usuarios->actualizar($usuario, ['contrasena_cifrada' => $contrasenaNueva]);

        $idSesionActual = $usuario->currentAccessToken()?->getKey();
        $usuario->tokens()->when($idSesionActual, fn ($q) => $q->where('id', '!=', $idSesionActual))->delete();

        EventoAuditable::dispatch(TipoEvento::CONTRASENA_CAMBIADA, "{$usuario->correo} cambió su contraseña.", $usuario->id_usuario);
    }
}
