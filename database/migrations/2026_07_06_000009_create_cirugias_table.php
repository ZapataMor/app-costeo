<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cirugias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('sala_operatoria_id')->nullable()->constrained('salas_operatorias')->restrictOnDelete();
            $table->date('fecha');
            $table->dateTime('hora_inicio');
            $table->dateTime('hora_fin')->nullable();
            $table->string('tipo', 15)->default('programada'); // programada | urgencia
            $table->string('estado', 15)->default('realizada'); // programada | realizada | cancelada
            $table->string('diagnostico_cie10', 8)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cirugias');
    }
};
