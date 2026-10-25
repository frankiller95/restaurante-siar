<?php

namespace App\Contracts\Repositories;

use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Patrón Repository: los servicios dependen de esta interfaz, no de Eloquent.
 */
interface UsuarioRepositoryInterface
{
    /**
     * @param  array{buscar?: string|null, id_rol?: int|null, estado?: string|null}  $filtros
     */
    public function buscar(array $filtros, int $porPagina = 15): LengthAwarePaginator;

    public function obtenerPorId(int $idUsuario): ?Usuario;

    public function obtenerPorCorreo(string $correo): ?Usuario;

    public function existeCorreo(string $correo, ?int $exceptoId = null): bool;

    public function existeDocumento(string $documento, ?int $exceptoId = null): bool;

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Usuario;

    /** @param array<string, mixed> $datos */
    public function actualizar(Usuario $usuario, array $datos): Usuario;

    public function eliminar(Usuario $usuario): void;

    /** ¿Tiene pedidos, facturas o reservas asociados? (CU-03) */
    public function tieneDependencias(Usuario $usuario): bool;

    /** Revoca las sesiones de todos los usuarios de un rol. Devuelve cuántas cerró. */
    public function revocarSesionesPorRol(int $idRol, ?int $exceptoIdSesion = null): int;
}
