<?php

namespace App\Services;

use App\Contracts\Repositories\PermisoRepositoryInterface;
use App\Contracts\Repositories\RolRepositoryInterface;
use App\Contracts\Repositories\UsuarioRepositoryInterface;
use App\DTOs\RolDTO;
use App\Enums\CodigoPermiso;
use App\Enums\TipoEvento;
use App\Events\EventoAuditable;
use App\Exceptions\ReglaNegocioException;
use App\Models\Rol;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CU-04 Gestionar roles y permisos.
 */
class RolService
{
    public function __construct(
        private readonly RolRepositoryInterface $roles,
        private readonly PermisoRepositoryInterface $permisos,
        private readonly UsuarioRepositoryInterface $usuarios,
    ) {}

    /** @return Collection<int, Rol> */
    public function listar(): Collection
    {
        return $this->roles->todos();
    }

    public function crear(RolDTO $dto): Rol
    {
        $this->verificarNombreUnico($dto->nombreRol);

        $rol = $this->roles->crear([...$dto->aArray(), 'es_predefinido' => false]);

        EventoAuditable::dispatch(TipoEvento::ROL_CREADO, "Se creó el rol personalizado {$rol->nombre_rol}.");

        return $rol;
    }

    public function actualizar(Rol $rol, RolDTO $dto): Rol
    {
        // 3a: los roles del sistema solo se configuran (permisos).
        if ($rol->es_predefinido) {
            throw new ReglaNegocioException('Los roles del sistema no se pueden renombrar ni editar; solo se configuran sus permisos.');
        }

        $this->verificarNombreUnico($dto->nombreRol, $rol->id_rol);

        $anterior = $rol->nombre_rol;
        $rol = $this->roles->actualizar($rol, $dto->aArray());

        EventoAuditable::dispatch(TipoEvento::ROL_ACTUALIZADO, "Se actualizó el rol {$anterior} (ahora: {$rol->nombre_rol}).");

        return $rol;
    }

    public function eliminar(Rol $rol): void
    {
        if ($rol->es_predefinido) {
            throw new ReglaNegocioException('Los roles del sistema no se pueden eliminar.');
        }
        // 3b: no se elimina un rol con usuarios.
        if (! $rol->esEliminable()) {
            throw new ReglaNegocioException('No se puede eliminar el rol porque tiene usuarios asociados.', 409);
        }

        $nombre = $rol->nombre_rol;
        $this->roles->eliminar($rol);

        EventoAuditable::dispatch(TipoEvento::ROL_ELIMINADO, "Se eliminó el rol personalizado {$nombre}.");
    }

    /**
     * Reemplaza los permisos del rol. Si cambiaron, cierra las sesiones de los
     * usuarios del rol para que vuelvan a entrar con los permisos nuevos (paso 6).
     *
     * @param  list<int>  $idsPermiso
     * @param  int|null  $exceptoIdSesion  sesión de quien hace el cambio (no se cierra)
     * @return array{rol: Rol, sesiones_revocadas: int}
     */
    public function asignarPermisos(Rol $rol, array $idsPermiso, ?int $exceptoIdSesion = null): array
    {
        $codigos = $this->permisos->codigosPorIds($idsPermiso);

        if ($rol->esAdministrador() && ! in_array(CodigoPermiso::GESTIONAR_USUARIOS->value, $codigos, true)) {
            throw new ReglaNegocioException('El rol Administrador debe conservar el permiso "gestionar_usuarios".');
        }

        $revocadas = DB::transaction(function () use ($rol, $idsPermiso, $exceptoIdSesion) {
            if (! $this->roles->sincronizarPermisos($rol, $idsPermiso)) {
                return 0;
            }

            return $this->usuarios->revocarSesionesPorRol($rol->id_rol, $exceptoIdSesion);
        });

        $lista = $codigos === [] ? 'ninguno' : implode(', ', $codigos);
        EventoAuditable::dispatch(
            TipoEvento::PERMISOS_ACTUALIZADOS,
            "Permisos del rol {$rol->nombre_rol}: {$lista}. Sesiones cerradas: {$revocadas}.",
        );

        return ['rol' => $rol->loadCount('usuarios'), 'sesiones_revocadas' => $revocadas];
    }

    private function verificarNombreUnico(string $nombre, ?int $exceptoId = null): void
    {
        if ($this->roles->existeNombre($nombre, $exceptoId)) {
            throw ValidationException::withMessages(['nombre_rol' => 'Ya existe un rol con este nombre.']);
        }
    }
}
