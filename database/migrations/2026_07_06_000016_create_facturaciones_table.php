<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->unique()->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->decimal('valor_facturado', 14, 2);
            $table->decimal('valor_glosado', 14, 2)->default(0);
            $table->decimal('valor_recaudado', 14, 2)->default(0);
            $table->decimal('tarifa_referencia_soat', 14, 2)->nullable();
            $table->date('fecha_facturacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturaciones');
    }
};
