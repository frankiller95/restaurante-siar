<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 6 - Tabla FACTURAS (RF28 a RF32).
 *
 * Una factura es una "foto" del pedido entregado: guarda los valores
 * calculados (subtotal, descuentos, impuesto, propina y total) para que no
 * cambien si después cambian los precios del menú o la tarifa del impuesto.
 *
 * - numero: consecutivo legible (FAC-000001), se arma con el id al crearla.
 * - estado: pendiente (emitida, sin pago completo), pagada o anulada.
 * - descuento_tipo / descuento_valor: lo que digitó el cajero (RF30);
 *   descuento es el valor en pesos ya calculado.
 * - descuento_promociones: descuentos que el pedido ya traía (promociones del portal).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facturas')) {
            return;
        }

        Schema::create('facturas', function (Blueprint $table) {
            $table->id('id_factura');
            $table->string('numero', 20)->nullable()->unique();
            $table->foreignId('id_pedido')->constrained('pedidos', 'id_pedido')->restrictOnDelete();
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario')->restrictOnDelete();
            $table->enum('estado', ['pendiente', 'pagada', 'anulada'])->default('pendiente');

            $table->string('cliente_nombre', 160)->default('Consumidor final');
            $table->string('cliente_documento', 30)->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento_promociones', 12, 2)->default(0);
            $table->enum('descuento_tipo', ['ninguno', 'porcentaje', 'valor'])->default('ninguno');
            $table->decimal('descuento_valor', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->string('motivo_descuento', 255)->nullable();

            $table->string('impuesto_nombre', 40)->default('Impuesto');
            $table->decimal('impuesto_porcentaje', 5, 2)->default(0);
            $table->boolean('impuesto_incluido')->default(true);
            $table->decimal('base_gravable', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('propina', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('motivo_anulacion', 255)->nullable();
            $table->timestamp('fecha_emision')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->timestamp('fecha_anulacion')->nullable();
            $table->timestamp('comprobante_emitido_en')->nullable();
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();

            $table->index(['id_pedido', 'estado']);
            $table->index(['estado', 'fecha_emision']);
            $table->index('fecha_pago');
        });
    }

    public function down(): void
    {
        // Las facturas son registros contables: no se borran al revertir.
    }
};
