<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_cirugia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->unique()->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->decimal('costo_recurso_humano', 14, 2)->default(0);
            $table->decimal('costo_sala', 14, 2)->default(0);
            $table->decimal('costo_equipos', 14, 2)->default(0);
            $table->decimal('costo_insumos', 14, 2)->default(0);
            $table->decimal('costo_directo', 14, 2)->default(0);
            $table->decimal('costo_indirecto', 14, 2)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->json('detalle')->nullable(); // desglose línea a línea del cálculo
            $table->timestamp('calculado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_cirugia');
    }
};
