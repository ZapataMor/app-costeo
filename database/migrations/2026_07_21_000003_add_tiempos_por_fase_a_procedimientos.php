<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiempos estándar por fase del protocolo.
 *
 * `duracion_estimada_minutos` conserva su significado —el tiempo de sala— y
 * sigue siendo el único obligatorio; las tres fases nuevas son opcionales
 * para que los procedimientos ya cargados sigan siendo válidos mientras el
 * hospital levanta el dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedimientos_quirurgicos', function (Blueprint $table) {
            $table->unsignedSmallInteger('minutos_prequirurgico')->nullable()->after('duracion_estimada_minutos');
            $table->unsignedSmallInteger('minutos_recuperacion')->nullable()->after('minutos_prequirurgico');
            // Recambio: la sala sigue ocupada aunque no haya paciente, así que
            // es costo de quirófano que ningún procedimiento absorbe hoy.
            $table->unsignedSmallInteger('minutos_recambio')->nullable()->after('minutos_recuperacion');
        });
    }

    public function down(): void
    {
        Schema::table('procedimientos_quirurgicos', function (Blueprint $table) {
            $table->dropColumn(['minutos_prequirurgico', 'minutos_recuperacion', 'minutos_recambio']);
        });
    }
};
