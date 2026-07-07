<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumo_insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->decimal('cantidad', 10, 2);
            // Snapshot del costo al momento del consumo (el precio del insumo puede cambiar)
            $table->decimal('costo_unitario_registrado', 14, 2);
            $table->decimal('costo_total', 14, 2);
            $table->timestamps();

            $table->unique(['cirugia_id', 'insumo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumo_insumos');
    }
};
