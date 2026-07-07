<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('miembros_equipo_quirurgico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('recurso_humano_id')->constrained('recursos_humanos')->restrictOnDelete();
            $table->string('rol', 20); // rol desempeñado en esta cirugía
            $table->unsignedSmallInteger('minutos_participacion');
            $table->timestamps();

            $table->unique(['cirugia_id', 'recurso_humano_id', 'rol'], 'miembro_equipo_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miembros_equipo_quirurgico');
    }
};
