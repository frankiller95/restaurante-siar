<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 5 - Tabla DETALLE_RECETAS (RF38): qué insumos lleva una porción de
 * cada producto del menú. La cantidad está en la unidad de medida del insumo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_recetas', function (Blueprint $table) {
            $table->id('id_detalle_receta');
            $table->foreignId('id_producto')->constrained('productos', 'id_producto')->cascadeOnDelete();
            $table->foreignId('id_insumo')->constrained('insumos', 'id_insumo')->restrictOnDelete();
            $table->decimal('cantidad_por_porcion', 12, 3);

            $table->unique(['id_producto', 'id_insumo']);
            $table->index('id_insumo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_recetas');
    }
};
