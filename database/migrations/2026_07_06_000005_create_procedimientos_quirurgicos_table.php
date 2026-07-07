<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedimientos_quirurgicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('codigo_cups', 10);
            $table->string('nombre');
            $table->string('especialidad');
            $table->string('complejidad', 10)->default('media');
            $table->unsignedSmallInteger('duracion_estimada_minutos');
            $table->decimal('tarifa_soat', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'codigo_cups']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedimientos_quirurgicos');
    }
};
