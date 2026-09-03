<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capacidad práctica parametrizable (corte 1 del módulo CIF).
 *
 * Hasta ahora la capacidad TDABC asumía que los 60 minutos de cada hora son
 * productivos: minutos disponibles/mes = horas_dia × dias_mes × 60. El
 * instrumento de recolección de la tesis usa 40 minutos efectivos por hora
 * (12 × 26 × 40 = 12.480), un denominador 33 % menor que eleva un 50 % TODAS
 * las tarifas por minuto.
 *
 * El default de 60 reproduce exactamente el comportamiento anterior: ningún
 * costo ya calculado cambia al aplicar esta migración.
 *
 * `minutos_efectivos_hora_registrado` congela en la cirugía el valor usado.
 * No se rellena hacia atrás: `minutos_disponibles_mes_registrado` ya congela
 * el producto, y las cirugías anteriores se calcularon necesariamente con 60.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            $table->unsignedTinyInteger('minutos_efectivos_hora')
                ->default(60)
                ->after('dias_mes');
        });

        Schema::table('cirugias', function (Blueprint $table) {
            $table->unsignedTinyInteger('minutos_efectivos_hora_registrado')
                ->nullable()
                ->after('minutos_disponibles_mes_registrado');
        });
    }

    public function down(): void
    {
        Schema::table('hospitales', function (Blueprint $table) {
            $table->dropColumn('minutos_efectivos_hora');
        });

        Schema::table('cirugias', function (Blueprint $table) {
            $table->dropColumn('minutos_efectivos_hora_registrado');
        });
    }
};
