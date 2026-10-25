<?php

namespace App\Services;

use App\Contracts\Repositories\UsuarioRepositoryInterface;
use App\Contracts\Services\ServicioCorreo;
use App\DTOs\UsuarioDTO;
use App\Enums\EstadoUsuario;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CU-01 Registrar, CU-02 Editar y CU-03 Eliminar usuario.
 */
class UsuarioService
{
    public const ELIMINACION_LOGICA = 'logica';

    public const ELIMINACION_FISICA = 'fisica';

    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarios,
        private readonly ServicioCorreo $correo,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->usuarios->buscar($filtros, $porPagina);
    }

    /**
     * CU-01. Crea el usuario activo con una contraseña temporal y se la envía por correo.
     *
     * @return array{usuario: Usuario, correo_enviado: bool}
     */
    public function registrar(UsuarioDTO $dto): array
    {
        $this->verificarUnicidad($dto);

        $contrasenaTemporal = $this->generarContrasena();

        $usuario = $this->usuarios->crear([
            ...$dto->aArray(),
            'contrasena_cifrada' => $contrasenaTemporal,
            'estado' => EstadoUsuario::ACTIVO,
        ]);

        EventoAuditable::dispatch(
            TipoEvento::USUARIO_REGISTRADO,
            "Se registró el usuario {$usuario->nombres} ({$usuario->correo}) con el rol {$usuario->rol->nombre_rol}.",
        );

        // 7a: si el correo falla, el usuario se conserva y queda pendiente el envío.
        $enviado = $this->correo->enviarCredenciales($usuario->correo, $usuario->nombres, $contrasenaTemporal);
        if (! $enviado) {
            EventoAuditable::dispatch(TipoEvento::CORREO_NO_ENVIADO, "No se pudieron enviar las credenciales a {$usuario->correo}.");
        }

        return ['usuario' => $usuario, 'correo_enviado' => $enviado];
    }

    /**
     * CU-02. Si cambia el rol o el estado, se cierran las sesiones del usuario.
     *
     * @return array{usuario: Usuario, sesiones_revocadas: bool}
     */
    public function actualizar(Usuario $usuario, UsuarioDTO $dto, Usuario $actor): array
    {
        $esElMismo = $usuario->is($actor);

        if ($esElMismo && $dto->idRol !== $usuario->id_rol) {
            throw new ReglaNegocioException('No puedes cambiar tu propio rol.');
        }
        if ($esElMismo && $dto->estado === EstadoUsuario::INACTIVO) {
            throw new ReglaNegocioException('No puedes desactivar tu propia cuenta.');
        }

        $this->verificarUnicidad($dto, $usuario->id_usuario);

        $camposCambiados = $this->camposCambiados($usuario, $dto);
        if ($camposCambiados === []) {
            return ['usuario' => $usuario->loadMissing('rol'), 'sesiones_revocadas' => false];
        }

        $cambioAcceso = in_array('rol', $camposCambiados, true) || in_array('estado', $camposCambiados, true);

        $usuario = DB::transaction(function () use ($usuario, $dto, $cambioAcceso) {
            $actualizado = $this->usuarios->actualizar($usuario, $dto->aArray());
            if ($cambioAcceso) {
                $actualizado->revocarSesiones();
            }

            return $actualizado;
        });

        $lista = implode(', ', $camposCambiados);
        EventoAuditable::dispatch(
            TipoEvento::USUARIO_ACTUALIZADO,
            "Se actualizó el usuario {$usuario->nombres} ({$usuario->correo}). Campos: {$lista}.",
        );

        $this->correo->enviarNotificacionCambio(
            $usuario->correo,
            $usuario->nombres,
            "El administrador actualizó tu cuenta (campos: {$lista}).".($cambioAcceso ? ' Debes iniciar sesión de nuevo.' : ''),
        );

        return ['usuario' => $usuario, 'sesiones_revocadas' => $cambioAcceso];
    }

    /**
     * CU-03. Eliminación lógica (inactivar) si el usuario tiene pedidos, facturas
     * o reservas; si no, se borra el registro.
     *
     * @return string self::ELIMINACION_LOGICA o self::ELIMINACION_FISICA
     */
    public function eliminar(Usuario $usuario, Usuario $actor): string
    {
        if ($usuario->is($actor)) {
            throw new ReglaNegocioException('No puedes eliminar tu propia cuenta.');
        }

        $datos = "{$usuario->nombres} ({$usuario->correo})";

        if ($this->usuarios->tieneDependencias($usuario)) {
            if (! $usuario->estaActivo()) {
                throw new ReglaNegocioException('El usuario ya está inactivo y tiene historial asociado, por eso no se puede borrar.');
            }

            DB::transaction(function () use ($usuario) {
                $usuario->revocarSesiones();
                $this->usuarios->actualizar($usuario, ['estado' => EstadoUsuario::INACTIVO]);
            });

            EventoAuditable::dispatch(TipoEvento::USUARIO_INACTIVADO, "Se inactivó (eliminación lógica) al usuario {$datos} porque tiene historial asociado.");
            $this->correo->enviarNotificacionCambio($usuario->correo, $usuario->nombres, 'Tu cuenta en SIAR fue desactivada por el administrador.');

            return self::ELIMINACION_LOGICA;
        }

        $correo = $usuario->correo;
        $nombres = $usuario->nombres;

        DB::transaction(function () use ($usuario) {
            $usuario->revocarSesiones();
            $this->usuarios->eliminar($usuario);
        });

        EventoAuditable::dispatch(TipoEvento::USUARIO_ELIMINADO, "Se eliminó definitivamente al usuario {$datos}.");
        $this->correo->enviarNotificacionCambio($correo, $nombres, 'Tu cuenta en SIAR fue eliminada por el administrador.');

        return self::ELIMINACION_FISICA;
    }

    /**
     * Genera una nueva contraseña temporal y la envía (útil cuando falló el
     * correo del registro, CU-01 flujo 7a).
     */
    public function reenviarCredenciales(Usuario $usuario): bool
    {
        if (! $usuario->estaActivo()) {
            throw new ReglaNegocioException('No se pueden enviar credenciales a un usuario inactivo.');
        }

        $contrasenaTemporal = $this->generarContrasena();
        $this->usuarios->actualizar($usuario, ['contrasena_cifrada' => $contrasenaTemporal]);
        $usuario->revocarSesiones();

        $enviado = $this->correo->enviarCredenciales($usuario->correo, $usuario->nombres, $contrasenaTemporal);

        EventoAuditable::dispatch(
            $enviado ? TipoEvento::CREDENCIALES_REENVIADAS : TipoEvento::CORREO_NO_ENVIADO,
            $enviado
                ? "Se reenviaron las credenciales a {$usuario->correo}."
                : "No se pudieron reenviar las credenciales a {$usuario->correo}.",
        );

        return $enviado;
    }

    /** CU-01 paso 4 / CU-02 paso 5: correo y documento únicos. */
    private function verificarUnicidad(UsuarioDTO $dto, ?int $exceptoId = null): void
    {
        $errores = [];

        if ($this->usuarios->existeCorreo($dto->correo, $exceptoId)) {
            $errores['correo'] = 'Ya existe un usuario con este correo.';
        }
        if ($this->usuarios->existeDocumento($dto->documento, $exceptoId)) {
            $errores['documento'] = 'Ya existe un usuario con este documento.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /** @return list<string> */
    private function camposCambiados(Usuario $usuario, UsuarioDTO $dto): array
    {
        $cambios = [];

        if ($usuario->nombres !== $dto->nombres) {
            $cambios[] = 'nombres';
        }
        if ($usuario->documento !== $dto->documento) {
            $cambios[] = 'documento';
        }
        if ($usuario->correo !== $dto->correo) {
            $cambios[] = 'correo';
        }
        if ($usuario->id_rol !== $dto->idRol) {
            $cambios[] = 'rol';
        }
        if ($dto->estado !== null && $usuario->estado !== $dto->estado) {
            $cambios[] = 'estado';
        }

        return $cambios;
    }

    private function generarContrasena(): string
    {
        return Str::password(12, symbols: false);
    }
}
