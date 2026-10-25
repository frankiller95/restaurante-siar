<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 1 - Tablas ROLES, PERMISOS y ROL_PERMISOS (ver docs/MODELO_ER.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id('id_rol');
            $table->string('nombre_rol', 50)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->boolean('es_predefinido')->default(false);
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id('id_permiso');
            $table->string('codigo_permiso', 60)->unique();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
        });

        Schema::create('rol_permisos', function (Blueprint $table) {
            $table->foreignId('id_rol')->constrained('roles', 'id_rol')->cascadeOnDelete();
            $table->foreignId('id_permiso')->constrained('permisos', 'id_permiso')->cascadeOnDelete();
            $table->primary(['id_rol', 'id_permiso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permisos');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('roles');
    }
};
