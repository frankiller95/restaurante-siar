<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 6 - Tabla PAGOS (RF31).
 *
 * Una factura admite varios pagos (pago dividido: por ejemplo, una parte en
 * efectivo y otra con tarjeta).
 *
 * - monto: lo que se abona a la factura.
 * - monto_recibido / cambio: solo en efectivo (lo que entregó el cliente y lo
 *   que se le devuelve).
 * - referencia: número de aprobación o de la transferencia (tarjeta y transferencia).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos')) {
            return;
        }

        Schema::create('pagos', function (Blueprint $table) {
            $table->id('id_pago');
            $table->foreignId('id_factura')->constrained('facturas', 'id_factura')->restrictOnDelete();
            $table->enum('metodo', ['efectivo', 'tarjeta', 'transferencia']);
            $table->decimal('monto', 12, 2);
            $table->decimal('monto_recibido', 12, 2)->nullable();
            $table->decimal('cambio', 12, 2)->default(0);
            $table->string('referencia', 60)->nullable();
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario')->restrictOnDelete();
            $table->timestamp('fecha');
            $table->timestamp('fecha_creacion')->nullable();

            $table->index(['id_factura', 'fecha']);
            $table->index(['metodo', 'fecha']);
        });
    }

    public function down(): void
    {
        // Los pagos son registros contables: no se borran al revertir.
    }
};
