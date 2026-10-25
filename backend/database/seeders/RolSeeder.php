<?php

namespace Database\Seeders;

use App\Enums\CodigoPermiso as P;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Crea los 4 roles del sistema (RF04). Los permisos por defecto solo se asignan
 * cuando el rol se crea, para no pisar lo que el administrador configure después.
 * El Administrador siempre conserva todos los permisos.
 */
class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Rol::ADMINISTRADOR => [
                'descripcion' => 'Control total del sistema.',
                'permisos' => array_map(fn (P $p) => $p->value, P::cases()),
            ],
            Rol::CAJERO => [
                'descripcion' => 'Factura pedidos y registra pagos.',
                'permisos' => [P::CONSULTAR_MENU->value, P::CONSULTAR_MESAS->value, P::GESTIONAR_RESERVAS->value, P::FACTURAR->value],
            ],
            Rol::MESERO => [
                'descripcion' => 'Atiende mesas y toma pedidos.',
                'permisos' => [P::CONSULTAR_MENU->value, P::CONSULTAR_MESAS->value, P::GESTIONAR_RESERVAS->value, P::TOMAR_PEDIDOS->value],
            ],
            Rol::COCINERO => [
                'descripcion' => 'Prepara los pedidos enviados a cocina.',
                'permisos' => [P::CONSULTAR_MENU->value, P::PREPARAR_PEDIDOS->value],
            ],
        ];

        foreach ($roles as $nombre => $datos) {
            $rol = Rol::firstOrCreate(
                ['nombre_rol' => $nombre],
                ['descripcion' => $datos['descripcion'], 'es_predefinido' => true],
            );

            if ($rol->wasRecentlyCreated || $nombre === Rol::ADMINISTRADOR) {
                $ids = Permiso::whereIn('codigo_permiso', $datos['permisos'])->pluck('id_permiso');
                $rol->permisos()->sync($ids);
            }
        }
    }
}
