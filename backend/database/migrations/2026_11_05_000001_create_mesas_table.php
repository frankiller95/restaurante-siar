<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 3 - Tabla MESAS (ver docs/MODELO_ER.md).
 *
 * "estado" lo maneja el patrón State (App\Estados\Mesa). "activo" y
 * "fecha_baja" son la baja lógica (trait TieneBajaLogica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id('id_mesa');
            $table->unsignedSmallInteger('numero')->unique();
            $table->unsignedTinyInteger('capacidad');
            $table->string('ubicacion', 60)->nullable();
            $table->enum('estado', ['libre', 'ocupada', 'reservada', 'mantenimiento'])->default('libre');
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();
            $table->timestamp('fecha_baja')->nullable();

            $table->index(['activo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
