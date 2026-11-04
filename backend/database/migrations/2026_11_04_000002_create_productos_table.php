<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo 2 - Tabla PRODUCTOS (ver docs/MODELO_ER.md).
 *
 * El nombre NO es único: el CU01 permite repetirlo en una categoría si el
 * administrador lo confirma. "activo = false" es la baja lógica del CU03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id('id_producto');
            $table->string('nombre', 120);
            $table->string('descripcion', 500)->nullable();
            $table->decimal('precio', 12, 2);
            $table->string('imagen_url', 255)->nullable();
            $table->boolean('disponible')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_creacion')->nullable();
            $table->timestamp('fecha_modificacion')->nullable();
            $table->timestamp('fecha_baja')->nullable();
            $table->foreignId('id_categoria')->constrained('categorias', 'id_categoria')->restrictOnDelete();

            $table->index(['id_categoria', 'nombre']);
            $table->index(['activo', 'disponible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
