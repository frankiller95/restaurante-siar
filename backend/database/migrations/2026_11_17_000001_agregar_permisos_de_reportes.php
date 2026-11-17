<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo 7 - Permisos de reportes (RF33 a RF37). No crea tablas.
 *
 * Migración de datos (mismo estilo que "agregar_permiso_gestionar_disponibilidad"):
 * llega también a bases ya instaladas (servidor) con un simple "php artisan migrate".
 *
 * - ver_reportes             → Administrador: todos los reportes.
 * - ver_reportes_operativos  → Administrador y Cajero: solo consumo de inventario
 *                              y ocupación de mesas.
 *
 * Solo inserta lo que falta y nunca quita permisos que alguien ya haya asignado.
 */
return new class extends Migration
{
    /** @var array<string, array{nombre: string, descripcion: string, roles: list<string>}> */
    private const PERMISOS = [
        'ver_reportes' => [
            'nombre' => 'Ver reportes gerenciales',
            'descripcion' => 'Consultar todos los reportes: ventas, productos más vendidos, inventario, ocupación de mesas e indicadores financieros.',
            'roles' => ['Administrador'],
        ],
        'ver_reportes_operativos' => [
            'nombre' => 'Ver reportes operativos',
            'descripcion' => 'Consultar solo los reportes operativos: consumo de inventario y ocupación de mesas.',
            'roles' => ['Administrador', 'Cajero'],
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $codigo => $datos) {
            DB::table('permisos')->updateOrInsert(
                ['codigo_permiso' => $codigo],
                ['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion']],
            );

            $idPermiso = DB::table('permisos')->where('codigo_permiso', $codigo)->value('id_permiso');
            $roles = DB::table('roles')->whereIn('nombre_rol', $datos['roles'])->pluck('id_rol');

            foreach ($roles as $idRol) {
                DB::table('rol_permisos')->insertOrIgnore(['id_rol' => $idRol, 'id_permiso' => $idPermiso]);
            }
        }
    }

    public function down(): void
    {
        // Solo se retira el permiso nuevo; "ver_reportes" ya existía en el catálogo.
        $idPermiso = DB::table('permisos')->where('codigo_permiso', 'ver_reportes_operativos')->value('id_permiso');

        if ($idPermiso) {
            DB::table('rol_permisos')->where('id_permiso', $idPermiso)->delete();
            DB::table('permisos')->where('id_permiso', $idPermiso)->delete();
        }
    }
};
