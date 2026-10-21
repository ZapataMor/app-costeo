<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costo estándar vs. real (backlog #3) y clasificación de la desviación
 * (backlog #1).
 *
 * Hasta aquí la única vara de medir de una cirugía era la mediana de sus
 * pares: dice si un caso es raro, no si el protocolo se cumplió. El costo
 * estándar es lo que la cirugía *debió* costar según su protocolo (duración
 * estimada, plantilla de personal, equipos e insumos) con las mismas tarifas
 * congeladas del caso; la diferencia contra el real aísla la ineficiencia de
 * uso de la variación de precios.
 *
 * La clasificación sigue el pipeline doctoral (Subejecución ≤ −5 %, En rango,
 * Sobrecosto leve 5–20 %, Sobrecosto alto > 20 %), con umbrales por hospital
 * porque el profesor aún no los confirma [VERIFICAR].
 *
 * Nada cambia en el costo: son columnas nuevas, nulas hasta recostear.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            $table->decimal('umbral_subejecucion', 5, 4)->default(-0.05)->after('factor_indirecto');
            $table->decimal('umbral_sobrecosto_leve', 5, 4)->default(0.05)->after('umbral_subejecucion');
            $table->decimal('umbral_sobrecosto_alto', 5, 4)->default(0.20)->after('umbral_sobrecosto_leve');
        });

        Schema::table('costos_cirugia', function (Blueprint $table) {
            $table->decimal('costo_estandar', 14, 2)->nullable()->after('costo_total');
            $table->decimal('desviacion', 14, 2)->nullable()->after('costo_estandar');
            $table->decimal('desviacion_pct', 8, 4)->nullable()->after('desviacion');
            $table->string('clasificacion_desviacion', 20)->nullable()->after('desviacion_pct');

            $table->index(['hospital_id', 'clasificacion_desviacion'], 'costos_cirugia_hospital_clasificacion_idx');
        });
    }

    public function down(): void
    {
        Schema::table('costos_cirugia', function (Blueprint $table) {
            $table->dropIndex('costos_cirugia_hospital_clasificacion_idx');
            $table->dropColumn(['costo_estandar', 'desviacion', 'desviacion_pct', 'clasificacion_desviacion']);
        });

        Schema::table('hospitales', function (Blueprint $table) {
            $table->dropColumn(['umbral_subejecucion', 'umbral_sobrecosto_leve', 'umbral_sobrecosto_alto']);
        });
    }
};
