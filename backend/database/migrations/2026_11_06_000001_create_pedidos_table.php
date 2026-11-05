<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 4 - Tabla PEDIDOS (RF17 a RF22).
 *
 * Las fechas de cada paso (envío a cocina, preparación, listo, entrega,
 * cancelación) quedan registradas para consultar el estado y para reportes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id('id_pedido');
            $table->foreignId('id_mesa')->constrained('mesas', 'id_mesa')->restrictOnDelete();
            $table->foreignId('id_usuario')->constrained('usuarios', 'id_usuario')->restrictOnDelete();
            $table->enum('estado', ['pendiente', 'en_preparacion', 'listo', 'entregado', 'facturado', 'cancelado'])
                ->default('pendiente');
            $table->string('observaciones', 255)->nullable();
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->timestamp('fecha_envio_cocina')->nullable();
            $table->timestamp('fecha_inicio_preparacion')->nullable();
            $table->timestamp('fecha_listo')->nullable();
            $table->timestamp('fecha_entrega')->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();

            $table->index(['id_mesa', 'estado']);
            $table->index(['estado', 'fecha_envio_cocina']);
            $table->index('fecha_creacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
