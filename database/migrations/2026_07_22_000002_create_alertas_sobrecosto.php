<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas de sobrecosto: el caso atípico, congelado y con dueño.
 *
 * Hasta ahora el outlier solo existía como cálculo en vivo del dashboard de
 * Outliers: aparecía si alguien entraba a mirar y desaparecía en cuanto el
 * baseline se movía, sin dejar rastro de si se revisó ni de por qué ocurrió.
 * Esta tabla lo convierte en un hecho con estado: se detecta al costear, se
 * congela con las cifras del momento y queda pendiente hasta que alguien le
 * atribuye una causa.
 *
 * Las cifras se guardan y no se recalculan a propósito. El baseline de un
 * procedimiento cambia con cada cirugía nueva, así que un exceso recalculado
 * meses después no es el que se detectó; para auditar una revisión hay que
 * poder ver contra qué se comparó ese día.
 *
 * Una alerta viva por cirugía (índice único): recostear reemplaza la alerta,
 * no la duplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_sobrecosto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitales')->cascadeOnDelete();
            $table->foreignId('cirugia_id')->unique()->constrained('cirugias')->cascadeOnDelete();
            $table->foreignId('procedimiento_quirurgico_id')
                ->constrained('procedimientos_quirurgicos')
                ->cascadeOnDelete();

            // Cifras congeladas al momento de la detección.
            $table->decimal('costo_total', 14, 2);
            // Mediana del procedimiento, no la media: con n pequeño la media
            // se contamina con el propio outlier que se quiere medir.
            $table->decimal('costo_esperado', 14, 2);
            $table->decimal('exceso', 14, 2);
            $table->decimal('exceso_pct', 8, 4);
            $table->decimal('z', 8, 3)->nullable();
            $table->json('criterios');
            $table->unsignedInteger('n_baseline');

            // Descomposición del exceso por componente TDABC y componente que
            // más aporta: es lo que se le muestra al revisor para que no tenga
            // que reconstruir a mano de dónde salió la diferencia.
            $table->json('atribucion');
            $table->string('componente_dominante', 20);

            $table->string('estado', 12)->default('pendiente');
            $table->string('causa', 40)->nullable();
            $table->text('causa_detalle')->nullable();
            $table->foreignId('revisado_por')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamp('detectado_en');
            $table->timestamps();

            // La bandeja se consulta siempre por hospital + estado.
            $table->index(['hospital_id', 'estado'], 'alertas_hospital_estado');
            // Y el análisis de Capa 4 agrupa por procedimiento y causa.
            $table->index(['procedimiento_quirurgico_id', 'causa'], 'alertas_procedimiento_causa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_sobrecosto');
    }
};
