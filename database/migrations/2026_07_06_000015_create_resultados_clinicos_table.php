<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados_clinicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->unique()->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();
            $table->boolean('complicacion_intraoperatoria')->default(false);
            $table->text('descripcion_complicacion_intra')->nullable();
            $table->boolean('complicacion_postoperatoria')->default(false);
            $table->text('descripcion_complicacion_post')->nullable();
            $table->unsignedSmallInteger('dias_estancia')->default(0);
            $table->boolean('reingreso_30_dias')->default(false);
            $table->boolean('mortalidad')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados_clinicos');
    }
};
