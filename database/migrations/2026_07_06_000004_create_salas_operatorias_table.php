<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salas_operatorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('nombre');
            $table->string('ubicacion')->nullable();
            $table->decimal('costo_hora', 14, 2);
            $table->json('equipamiento')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salas_operatorias');
    }
};
