<?php

namespace App\Services;

use App\Contracts\Repositories\UsuarioRepositoryInterface;
use App\Contracts\Services\ServicioCorreo;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use Illuminate\Support\Facades\Password;

/**
 * CU-06 Recuperar contraseña. Usa el "password broker" de Laravel para crear
 * tokens de un solo uso que vencen (tabla solicitudes_recuperacion).
 */
class RecuperacionContrasenaService
{
    public const MENSAJE_SOLICITUD = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';

    public const MENSAJE_ENLACE_INVALIDO = 'El enlace no es válido o ya expiró. Solicita uno nuevo.';

    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly ServicioCorreo $correo,
    ) {}

    /**
     * Siempre termina igual para no revelar si el correo existe (flujo 3a).
     */
    public function solicitar(string $correo): void
    {
        $usuario = $this->usuarios->obtenerPorCorreo($correo);

        if (! $usuario || ! $usuario->estaActivo()) {
            EventoAuditable::dispatch(
                TipoEvento::RECUPERACION_SOLICITADA,
                "Solicitud de recuperación para un correo no registrado o inactivo ({$correo}). No se envió enlace.",
                $usuario?->id_usuario,
            );

            return;
        }

        $token = Password::broker()->createToken($usuario);
        $enlace = rtrim(config('siar.frontend_url'), '/').'/restablecer-contrasena?'.http_build_query([
            'token' => $token,
            'correo' => $usuario->correo,
        ]);

        $enviado = $this->correo->enviarEnlaceRecuperacion($usuario->correo, $usuario->nombres, $enlace);

        EventoAuditable::dispatch(
            $enviado ? TipoEvento::RECUPERACION_SOLICITADA : TipoEvento::CORREO_NO_ENVIADO,
            $enviado
                ? "Se envió enlace de recuperación a {$usuario->correo}."
                : "No se pudo enviar el enlace de recuperación a {$usuario->correo}.",
            $usuario->id_usuario,
        );
    }

    public function restablecer(string $correo, string $token, string $contrasenaNueva): void
    {
        $usuario = $this->usuarios->obtenerPorCorreo($correo);
        if (! $usuario || ! $usuario->estaActivo()) {
            throw new ReglaNegocioException(self::MENSAJE_ENLACE_INVALIDO);
        }

        $estado = Password::broker()->reset(
            ['correo' => $usuario->correo, 'token' => $token, 'password' => $contrasenaNueva],
            function (Usuario $usuario, string $contrasena) {
                $this->usuarios->actualizar($usuario, ['contrasena_cifrada' => $contrasena]);
                $usuario->revocarSesiones();
            },
        );

        // 5a: enlace vencido o ya usado (el broker borra el token al usarlo).
        if ($estado !== Password::PASSWORD_RESET) {
            throw new ReglaNegocioException(self::MENSAJE_ENLACE_INVALIDO);
        }

        EventoAuditable::dispatch(
            TipoEvento::CONTRASENA_RESTABLECIDA,
            "{$usuario->correo} restableció su contraseña con un enlace de recuperación.",
            $usuario->id_usuario,
        );
    }
}
