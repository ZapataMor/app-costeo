<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite capturar la participación del equipo quirúrgico por hora de
 * entrada y salida, igual que la cirugía, en vez de teclear los minutos.
 *
 * Los minutos se siguen guardando (son la base del costo TDABC y del
 * histórico ya registrado); las horas son opcionales y solo documentan de
 * dónde salieron esos minutos cuando se capturan así.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miembros_equipo_quirurgico', function (Blueprint $table) {
            $table->dateTime('hora_inicio')->nullable()->after('rol');
            $table->dateTime('hora_fin')->nullable()->after('hora_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('miembros_equipo_quirurgico', function (Blueprint $table) {
            $table->dropColumn(['hora_inicio', 'hora_fin']);
        });
    }
};
