<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit', 20)->unique();
            $table->string('nivel_complejidad', 20)->default('mediana');
            $table->string('municipio')->nullable();
            $table->string('departamento')->default('La Guajira');
            // Parámetros TDABC: minutos disponibles/mes = horas_dia × dias_mes × 60
            $table->unsignedTinyInteger('horas_dia')->default(12);
            $table->unsignedTinyInteger('dias_mes')->default(26);
            // Proporción de costos indirectos aplicada sobre el costo directo
            $table->decimal('factor_indirecto', 5, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitales');
    }
};
