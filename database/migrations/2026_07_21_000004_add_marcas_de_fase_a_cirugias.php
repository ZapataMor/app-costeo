<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas de tiempo de las tres fases del ciclo quirúrgico.
 *
 * `hora_inicio` y `hora_fin` no cambian de significado —siguen siendo la
 * entrada y la salida de sala, y siguen siendo la base del costo de
 * quirófano—; estas cuatro marcas las rodean:
 *
 *   ingreso_paciente → [hora_inicio → incision → cierre → hora_fin] → salida_recuperacion
 *   └─ pre-quirúrgico ─┘└──────────── quirúrgico ────────────┘└── post-quirúrgico ──┘
 *
 * Separar incisión y cierre del tiempo de sala es lo que permite ver la
 * ineficiencia de quirófano: sala ocupada sin estar operando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cirugias', function (Blueprint $table) {
            $table->dateTime('hora_ingreso_paciente')->nullable()->after('fecha');
            $table->dateTime('hora_incision')->nullable()->after('hora_inicio');
            $table->dateTime('hora_cierre')->nullable()->after('hora_incision');
            $table->dateTime('hora_salida_recuperacion')->nullable()->after('hora_fin');
        });
    }

    public function down(): void
    {
        Schema::table('cirugias', function (Blueprint $table) {
            $table->dropColumn([
                'hora_ingreso_paciente',
                'hora_incision',
                'hora_cierre',
                'hora_salida_recuperacion',
            ]);
        });
    }
};
