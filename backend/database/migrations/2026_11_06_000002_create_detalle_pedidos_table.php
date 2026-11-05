<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 4 - Tabla DETALLE_PEDIDOS (RF18).
 *
 * precio_unitario es una copia del precio del producto al momento de pedirlo:
 * si el administrador cambia el precio después, el pedido no cambia (CU02 del menú).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pedidos', function (Blueprint $table) {
            $table->id('id_detalle');
            $table->foreignId('id_pedido')->constrained('pedidos', 'id_pedido')->cascadeOnDelete();
            $table->foreignId('id_producto')->constrained('productos', 'id_producto')->restrictOnDelete();
            $table->unsignedSmallInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->string('observacion', 255)->nullable();

            $table->index('id_producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pedidos');
    }
};
