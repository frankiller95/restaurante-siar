<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo 2 - CU05: el Cocinero gestiona la disponibilidad de los productos.
 *
 * Migración de datos: crea el permiso "gestionar_disponibilidad" y lo asigna a
 * los roles Administrador y Cocinero. Así llega también a bases ya instaladas
 * (servidor) con un simple "php artisan migrate", sin volver a sembrar datos.
 */
return new class extends Migration
{
    private const CODIGO = 'gestionar_disponibilidad';

    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['codigo_permiso' => self::CODIGO],
            [
                'nombre' => 'Gestionar disponibilidad del menú',
                'descripcion' => 'Marcar productos como disponibles o agotados en tiempo real.',
            ],
        );

        $idPermiso = DB::table('permisos')->where('codigo_permiso', self::CODIGO)->value('id_permiso');
        $roles = DB::table('roles')->whereIn('nombre_rol', ['Administrador', 'Cocinero'])->pluck('id_rol');

        foreach ($roles as $idRol) {
            DB::table('rol_permisos')->insertOrIgnore(['id_rol' => $idRol, 'id_permiso' => $idPermiso]);
        }
    }

    public function down(): void
    {
        $idPermiso = DB::table('permisos')->where('codigo_permiso', self::CODIGO)->value('id_permiso');

        if ($idPermiso) {
            DB::table('rol_permisos')->where('id_permiso', $idPermiso)->delete();
            DB::table('permisos')->where('id_permiso', $idPermiso)->delete();
        }
    }
};
