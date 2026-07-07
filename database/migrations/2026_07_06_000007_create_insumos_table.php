<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre');
            $table->string('categoria', 15); // medicamento | dispositivo | material
            $table->string('codigo_atc', 10)->nullable(); // obligatorio para medicamentos
            $table->string('unidad', 20);
            $table->decimal('costo_unitario', 14, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
