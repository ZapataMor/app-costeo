<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recursos_humanos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->string('nombre');
            $table->string('rol', 20);
            $table->string('especialidad')->nullable();
            // costo/minuto = (salario + prestaciones + indirectos) / minutos disponibles del hospital
            $table->decimal('salario_mensual', 14, 2);
            $table->decimal('prestaciones_mensuales', 14, 2)->default(0);
            $table->decimal('costos_indirectos_mensuales', 14, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['hospital_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recursos_humanos');
    }
};
