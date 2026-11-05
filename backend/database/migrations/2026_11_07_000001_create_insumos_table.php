<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 5 - Tabla INSUMOS (RF23, RF24).
 *
 * stock_actual no se edita directamente: cambia con los movimientos
 * (entradas, consumos, mermas y ajustes). "activo / fecha_baja" es la baja lógica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id('id_insumo');
            $table->string('nombre', 100)->unique();
            $table->enum('unidad_medida', ['kilogramo', 'gramo', 'litro', 'mililitro', 'unidad']);
            $table->decimal('stock_actual', 12, 3)->default(0);
            $table->decimal('stock_minimo', 12, 3)->default(0);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();
            $table->timestamp('fecha_baja')->nullable();

            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
