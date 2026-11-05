<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 5 - RF40: marca los productos que el inventario dejó como no
 * disponibles. Así, al reponer el insumo, solo se reactivan esos y no los que
 * el cocinero marcó como agotados a mano (CU05 del Módulo 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('agotado_por_inventario')->default(false)->after('disponible');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('agotado_por_inventario');
        });
    }
};
