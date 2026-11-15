<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 6 - Tabla DETALLE_FACTURAS (RF28).
 *
 * Copia de cada línea del pedido al momento de facturar. El nombre y las
 * adiciones se guardan como texto: la factura no debe cambiar si el producto
 * se renombra o se da de baja después.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('detalle_facturas')) {
            return;
        }

        Schema::create('detalle_facturas', function (Blueprint $table) {
            $table->id('id_detalle_factura');
            $table->foreignId('id_factura')->constrained('facturas', 'id_factura')->cascadeOnDelete();
            $table->foreignId('id_producto')->nullable()->constrained('productos', 'id_producto')->nullOnDelete();
            $table->string('descripcion', 160);
            $table->string('adiciones', 255)->nullable();
            $table->unsignedSmallInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);

            $table->index('id_factura');
        });
    }

    public function down(): void
    {
        // Se conserva el detalle de las facturas emitidas.
    }
};
