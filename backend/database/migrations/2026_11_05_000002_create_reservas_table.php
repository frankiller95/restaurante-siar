<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 3 - Tabla RESERVAS (RF15, ver docs/MODELO_ER.md).
 *
 * "id_usuario" es el usuario del personal que registró la reserva; los datos
 * del cliente van en cliente_nombre y cliente_telefono. "fecha" y "hora" están
 * en la zona horaria del restaurante (config/siar.php > zona_horaria).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id('id_reserva');
            $table->date('fecha');
            $table->time('hora');
            $table->unsignedTinyInteger('cantidad_personas');
            $table->enum('estado', ['pendiente', 'cumplida', 'cancelada', 'no_asistio'])->default('pendiente');
            $table->string('cliente_nombre', 120);
            $table->string('cliente_telefono', 20);
            $table->string('observaciones', 255)->nullable();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->restrictOnDelete();
            $table->foreignId('id_mesa')->constrained('mesas', 'id_mesa')->restrictOnDelete();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();

            $table->index(['id_mesa', 'fecha', 'estado']);
            $table->index(['fecha', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
