<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de asignación de bolsas CIF (corte 3 del módulo).
 *
 * Hasta aquí el catálogo se capturaba pero no entraba al costo: el indirecto
 * de toda cirugía era `costo_directo × factor_indirecto`. Estas dos piezas
 * cierran el ciclo.
 *
 * `cirugias.parametros_cif_registrados` congela CÓMO se costea la cirugía:
 * la vía aplicada (factor plano o bolsas), el origen de cada componente y el
 * denominador de cada base de asignación, con la lista de conceptos vigentes
 * y su tasa. Sin esto, recostear una cirugía de hace un año la recalcularía
 * con las salas y las bolsas de hoy.
 *
 * `cirugia_concepto_indirecto` guarda el resultado línea a línea: cuánto puso
 * cada bolsa en esa cirugía y con qué tasa y unidades. Es lo que permite
 * auditar un costo indirecto sin recorrer el catálogo ni rehacer la cuenta.
 *
 * Nada cambia para las cirugías ya registradas: sin snapshot, el motor cae en
 * la vía del factor plano, que es exactamente lo que hacía antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cirugias', function (Blueprint $table) {
            $table->json('parametros_cif_registrados')->nullable()->after('factor_indirecto_registrado');
        });

        Schema::create('cirugia_concepto_indirecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cirugia_id')->constrained('cirugias')->cascadeOnDelete();
            // La bolsa no se puede borrar mientras haya cirugías que la citen:
            // el histórico de costos dejaría de ser reproducible.
            $table->foreignId('concepto_costo_indirecto_id')
                ->constrained('conceptos_costo_indirecto')
                ->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitales')->restrictOnDelete();

            // Copia de lo que era el concepto al costear, para no depender del
            // catálogo al leer la ficha.
            $table->string('nombre_registrado');
            $table->string('categoria_registrada', 30);
            $table->string('base_asignacion_registrada', 30);
            $table->decimal('monto_mensual_registrado', 14, 2)->nullable();
            $table->decimal('porcentaje_registrado', 8, 4)->nullable();

            // Minutos de la ventana usada como denominador; null en la base
            // por porcentaje, que no divide nada.
            $table->unsignedInteger('denominador_registrado')->nullable();
            // Sin redondear a 2: es una tasa por minuto, no un peso. Un peso
            // por minuto redondeado a centavos deforma el total de una
            // cirugía larga.
            $table->decimal('tasa_registrada', 16, 6);
            $table->decimal('unidades_aplicadas', 12, 2);
            $table->decimal('monto_asignado', 14, 2);

            $table->timestamps();

            // Una bolsa aporta una sola línea por cirugía; recostear
            // reemplaza la fila, no la duplica.
            $table->unique(['cirugia_id', 'concepto_costo_indirecto_id'], 'cci_cirugia_concepto_uq');
            $table->index(['hospital_id', 'categoria_registrada'], 'cci_hospital_categoria_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cirugia_concepto_indirecto');

        Schema::table('cirugias', function (Blueprint $table) {
            $table->dropColumn('parametros_cif_registrados');
        });
    }
};
