<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla (protocolo) de cada procedimiento: lo que se usa siempre.
 *
 * Hasta ahora cada cirugía se capturaba desde cero, línea por línea, aunque
 * una colecistectomía consuma prácticamente lo mismo todas las veces. Eso
 * hace lento el registro y, peor, hace que lo capturado dependa de la memoria
 * de quien digita: lo que se olvida no se costea y el procedimiento aparece
 * más barato de lo que fue.
 *
 * La plantilla invierte el trabajo: el registro nace con lo estándar puesto y
 * el digitador solo marca la excepción —lo que se usó de más o lo que no se
 * usó—. De paso, la diferencia entre plantilla y realidad se vuelve un dato
 * en sí mismo: es la variabilidad del procedimiento.
 *
 * Vive colgada del procedimiento, no del hospital: el procedimiento ya está
 * aislado por `hospital_id`, así que la plantilla hereda ese aislamiento y no
 * necesita repetir la columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Insumos estándar: qué se consume y cuánto, en qué fase del ciclo.
        Schema::create('plantilla_insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedimiento_quirurgico_id')
                ->constrained('procedimientos_quirurgicos')
                ->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->string('fase', 12)->default('quirurgica');
            $table->decimal('cantidad', 10, 2);
            // Opcional: se sugiere pero no se prellena. Sirve para el insumo
            // que solo se usa en algunos casos (el implante de una talla, la
            // profilaxis del alérgico) sin ensuciar el registro típico.
            $table->boolean('opcional')->default(false);
            $table->timestamps();

            $table->unique(['procedimiento_quirurgico_id', 'insumo_id', 'fase'], 'plantilla_insumo_unico');
        });

        // Personal estándar: qué roles se necesitan y cuántas personas de cada
        // uno. La persona concreta es opcional —cambia con el turno—, pero se
        // puede fijar donde siempre la hace el mismo (el único anestesiólogo).
        Schema::create('plantilla_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedimiento_quirurgico_id')
                ->constrained('procedimientos_quirurgicos')
                ->cascadeOnDelete();
            $table->string('rol', 20);
            $table->string('fase', 12)->default('quirurgica');
            $table->unsignedTinyInteger('cantidad')->default(1);
            $table->foreignId('recurso_humano_id')->nullable()
                ->constrained('recursos_humanos')
                ->nullOnDelete();
            // Minutos típicos de participación. Nulo significa «lo que dure la
            // fase», que es el caso normal del equipo quirúrgico.
            $table->unsignedSmallInteger('minutos')->nullable();
            $table->boolean('opcional')->default(false);
            $table->timestamps();

            // Sin índice único: dos líneas del mismo rol y fase son legítimas
            // (dos ayudantes con personas distintas, o uno fijo y otro no).
            // La repetición exacta la rechaza la validación del formulario.
            $table->index(['procedimiento_quirurgico_id', 'fase'], 'plantilla_personal_fase');
        });

        // Equipos médicos estándar.
        Schema::create('plantilla_equipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procedimiento_quirurgico_id')
                ->constrained('procedimientos_quirurgicos')
                ->cascadeOnDelete();
            $table->foreignId('equipo_medico_id')->constrained('equipos_medicos')->restrictOnDelete();
            // Nulo: se usa todo el tiempo de sala.
            $table->unsignedSmallInteger('minutos_uso')->nullable();
            $table->boolean('opcional')->default(false);
            $table->timestamps();

            $table->unique(['procedimiento_quirurgico_id', 'equipo_medico_id'], 'plantilla_equipo_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantilla_equipos');
        Schema::dropIfExists('plantilla_personal');
        Schema::dropIfExists('plantilla_insumos');
    }
};
