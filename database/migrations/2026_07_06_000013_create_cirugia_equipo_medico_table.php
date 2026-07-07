<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cirugia_equipo_medico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('equipo_medico_id')->constrained('equipos_medicos')->restrictOnDelete();
            $table->unsignedSmallInteger('minutos_uso');
            $table->timestamps();

            $table->unique(['cirugia_id', 'equipo_medico_id'], 'cirugia_equipo_medico_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cirugia_equipo_medico');
    }
};
