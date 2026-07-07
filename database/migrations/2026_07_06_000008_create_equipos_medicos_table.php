<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos_medicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('nombre');
            $table->string('codigo', 30)->nullable();
            $table->decimal('valor_adquisicion', 14, 2)->nullable();
            $table->unsignedTinyInteger('vida_util_anios')->nullable();
            $table->decimal('costo_hora', 14, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos_medicos');
    }
};
