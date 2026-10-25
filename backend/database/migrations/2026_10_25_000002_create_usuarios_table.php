<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 1 - Tablas USUARIOS y SOLICITUDES_RECUPERACION (ver docs/MODELO_ER.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('nombres', 120);
            $table->string('documento', 20)->unique();
            $table->string('correo', 150)->unique();
            $table->string('contrasena_cifrada');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->foreignId('id_rol')->constrained('roles', 'id_rol')->restrictOnDelete();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
        });

        // Tabla usada por el "password broker" de Laravel para los enlaces de
        // recuperación: el token se guarda cifrado, vence (config/auth.php) y se
        // borra al usarse, por lo que es de un solo uso.
        Schema::create('solicitudes_recuperacion', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_recuperacion');
        Schema::dropIfExists('usuarios');
    }
};
