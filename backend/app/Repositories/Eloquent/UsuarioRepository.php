<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UsuarioRepositoryInterface;
use App\Models\Sesion;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsuarioRepository implements UsuarioRepositoryInterface
{
    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return Usuario::query()
            ->with('rol')
            ->when($filtros['buscar'] ?? null, function ($consulta, string $texto) {
                $consulta->where(function ($q) use ($texto) {
                    $q->where('nombres', 'like', "%{$texto}%")
                        ->orWhere('documento', 'like', "%{$texto}%")
                        ->orWhere('correo', 'like', "%{$texto}%");
                });
            })
            ->when($filtros['id_rol'] ?? null, fn ($q, $idRol) => $q->where('id_rol', $idRol))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->orderBy('nombres')
            ->paginate($porPagina);
    }

    public function obtenerPorId(int $idUsuario): ?Usuario
    {
        return Usuario::with('rol')->find($idUsuario);
    }

    public function obtenerPorCorreo(string $correo): ?Usuario
    {
        return Usuario::with('rol.permisos')->where('correo', mb_strtolower(trim($correo)))->first();
    }

    public function existeCorreo(string $correo, ?int $exceptoId = null): bool
    {
        return Usuario::where('correo', $correo)
            ->when($exceptoId, fn ($q) => $q->where('id_usuario', '!=', $exceptoId))
            ->exists();
    }

    public function existeDocumento(string $documento, ?int $exceptoId = null): bool
    {
        return Usuario::where('documento', $documento)
            ->when($exceptoId, fn ($q) => $q->where('id_usuario', '!=', $exceptoId))
            ->exists();
    }

    public function crear(array $datos): Usuario
    {
        return Usuario::create($datos)->load('rol');
    }

    public function actualizar(Usuario $usuario, array $datos): Usuario
    {
        $usuario->fill($datos)->save();

        return $usuario->load('rol');
    }

    public function eliminar(Usuario $usuario): void
    {
        $usuario->delete();
    }

    public function tieneDependencias(Usuario $usuario): bool
    {
        foreach (config('siar.dependencias_usuario', []) as $dependencia) {
            if (! Schema::hasTable($dependencia['tabla'])) {
                continue;
            }

            $existe = DB::table($dependencia['tabla'])
                ->where($dependencia['columna'], $usuario->id_usuario)
                ->exists();

            if ($existe) {
                return true;
            }
        }

        return false;
    }

    public function revocarSesionesPorRol(int $idRol, ?int $exceptoIdSesion = null): int
    {
        return Sesion::query()
            ->where('tokenable_type', (new Usuario)->getMorphClass())
            ->whereIn('tokenable_id', Usuario::where('id_rol', $idRol)->select('id_usuario'))
            ->when($exceptoIdSesion, fn ($q) => $q->where('id', '!=', $exceptoIdSesion))
            ->delete();
    }
}
