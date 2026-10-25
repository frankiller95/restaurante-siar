<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 1 - Tabla BITACORA_AUDITORIA (RNF06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_auditoria', function (Blueprint $table) {
            $table->id('id_bitacora');
            $table->string('tipo_evento', 60)->index();
            $table->string('descripcion', 500);
            $table->timestamp('fecha_hora')->useCurrent()->index();
            $table->string('ip_origen', 45)->nullable();
            // Si el usuario se borra físicamente, la bitácora se conserva.
            $table->foreignId('id_usuario')->nullable()->constrained('usuarios', 'id_usuario')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_auditoria');
    }
};
