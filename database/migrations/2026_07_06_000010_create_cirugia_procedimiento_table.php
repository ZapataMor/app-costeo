<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cirugia_procedimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('procedimiento_quirurgico_id')
                ->constrained('procedimientos_quirurgicos')
                ->restrictOnDelete();
            $table->boolean('es_principal')->default(false);
            $table->timestamps();

            $table->unique(['cirugia_id', 'procedimiento_quirurgico_id'], 'cirugia_procedimiento_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cirugia_procedimiento');
    }
};
