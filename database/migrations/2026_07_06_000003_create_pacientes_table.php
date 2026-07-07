<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('tipo_documento', 5)->default('CC');
            // Cifrado en reposo (Ley 1581/2012); el hash permite búsqueda y unicidad
            $table->text('documento');
            $table->string('documento_hash', 64);
            $table->string('nombres');
            $table->string('apellidos');
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 1)->nullable();
            $table->string('regimen', 20)->default('subsidiado');
            $table->string('asegurador')->nullable();
            $table->string('zona', 10)->default('urbana');
            $table->string('municipio')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'documento_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
