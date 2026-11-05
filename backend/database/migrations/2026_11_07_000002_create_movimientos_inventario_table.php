<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 5 - Tabla MOVIMIENTOS_INVENTARIO: el kárdex (RF25, RF26, RF27, RF39).
 *
 * "cantidad" lleva signo: positiva cuando entra al inventario (entrada,
 * reintegro, ajuste a favor) y negativa cuando sale (consumo, merma, ajuste en
 * contra). "stock_resultante" es el stock del insumo después del movimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id('id_movimiento');
            $table->foreignId('id_insumo')->constrained('insumos', 'id_insumo')->restrictOnDelete();
            $table->enum('tipo', ['entrada', 'consumo_pedido', 'reintegro_pedido', 'merma', 'ajuste']);
            $table->decimal('cantidad', 12, 3);
            $table->decimal('stock_resultante', 12, 3);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->string('proveedor', 120)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamp('fecha');
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario')->restrictOnDelete();
            $table->foreignId('id_pedido')->nullable()->constrained('pedidos', 'id_pedido')->restrictOnDelete();
            $table->timestamp('fecha_creacion')->nullable();

            $table->index(['id_insumo', 'fecha']);
            $table->index(['tipo', 'fecha']);
            $table->index('id_pedido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
